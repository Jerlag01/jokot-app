<?php

namespace App\Support\Payments;

use App\Enums\PaymentProvider;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout (hosted payment page: Bancontact, cards, ...), via the REST API.
 *
 * @see https://docs.stripe.com/api/checkout/sessions
 */
class StripeGateway implements PaymentGateway
{
    private const API = 'https://api.stripe.com/v1/';

    public function provider(): PaymentProvider
    {
        return PaymentProvider::Stripe;
    }

    public function isConfigured(): bool
    {
        return (bool) settings()->get('payments.stripe_enabled') && filled(settings()->secret('payments.stripe_secret'));
    }

    private function client(): PendingRequest
    {
        return Http::asForm()
            ->withBasicAuth((string) settings()->secret('payments.stripe_secret'), '')
            ->baseUrl(self::API)
            ->timeout(20);
    }

    public function startCheckout(Order $order): string
    {
        $order->loadMissing('items', 'event');
        $lineItems = [];

        foreach ($order->items as $index => $item) {
            $lineItems["line_items[{$index}][quantity]"] = $item->quantity;
            $lineItems["line_items[{$index}][price_data][currency]"] = 'eur';
            $lineItems["line_items[{$index}][price_data][unit_amount]"] = (int) round((float) $item->unit_price * 100);
            $lineItems["line_items[{$index}][price_data][product_data][name]"] = $order->event->title.' - '.$item->name;
        }

        $response = $this->client()->post('checkout/sessions', [
            'mode' => 'payment',
            'locale' => 'nl',
            'customer_email' => $order->email,
            'client_reference_id' => $order->number,
            'metadata[order_id]' => $order->id,
            'success_url' => $order->publicUrl().'?betaald=1',
            'cancel_url' => $order->publicUrl().'?geannuleerd=1',
            // Stripe's minimum is 30 minutes; the order keeps its tickets at least that long.
            'expires_at' => max(now()->addMinutes(31)->timestamp, $order->expires_at?->timestamp ?? 0),
            ...$lineItems,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? $response->status()));
        }

        $order->update(['provider_reference' => $response->json('id'), 'checkout_url' => $response->json('url')]);

        return (string) $response->json('url');
    }

    public function paidTransaction(Order $order): ?string
    {
        if (blank($order->provider_reference)) {
            return null;
        }

        $session = $this->client()->get('checkout/sessions/'.$order->provider_reference)->throw()->json();

        if (($session['payment_status'] ?? null) === 'paid') {
            return (string) ($session['payment_intent'] ?? $order->provider_reference);
        }

        if (($session['status'] ?? null) === 'expired') {
            throw new PaymentFailed('De betaalsessie is verlopen.');
        }

        return null;
    }

    public function refund(Order $order): void
    {
        if (blank($order->provider_transaction)) {
            throw new RuntimeException('Geen Stripe-betaling gevonden om terug te betalen.');
        }

        $response = $this->client()->post('refunds', ['payment_intent' => $order->provider_transaction]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe: '.($response->json('error.message') ?? $response->status()));
        }
    }

    /**
     * Checks the Stripe-Signature header (HMAC-SHA256 of "timestamp.payload", max. 5 minutes old).
     */
    public function verifyWebhook(string $payload, ?string $header): bool
    {
        $secret = settings()->secret('payments.stripe_webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($timestamp === 0 || abs(time() - $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, (string) $signature)) {
                return true;
            }
        }

        return false;
    }
}
