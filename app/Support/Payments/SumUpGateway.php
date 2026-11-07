<?php

namespace App\Support\Payments;

use App\Enums\PaymentProvider;
use App\Models\Order;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SumUp hosted checkout, via the REST API. SumUp's webhooks carry no signature, so the status is always
 * fetched from the API before an order is marked paid.
 *
 * @see https://developer.sumup.com/api/checkouts
 */
class SumUpGateway implements PaymentGateway
{
    private const API = 'https://api.sumup.com/v0.1/';

    public function provider(): PaymentProvider
    {
        return PaymentProvider::SumUp;
    }

    public function isConfigured(): bool
    {
        return (bool) settings()->get('payments.sumup_enabled')
            && filled(settings()->secret('payments.sumup_api_key'))
            && filled(settings()->get('payments.sumup_merchant_code'));
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->withToken((string) settings()->secret('payments.sumup_api_key'))
            ->baseUrl(self::API)
            ->timeout(20);
    }

    public function startCheckout(Order $order): string
    {
        $order->loadMissing('event');

        $response = $this->client()->post('checkouts', [
            // Unique per merchant; a retry for the same order gets a suffix.
            'checkout_reference' => $order->number.'-'.substr($order->token, 0, 6).'-'.now()->format('His'),
            'amount' => round((float) $order->total, 2),
            'currency' => 'EUR',
            'merchant_code' => settings()->get('payments.sumup_merchant_code'),
            'description' => $order->event->title.' - '.$order->number,
            'return_url' => route('webhooks.sumup'),
            'redirect_url' => $order->publicUrl().'?betaald=1',
            'hosted_checkout' => ['enabled' => true],
        ]);

        if ($response->failed() || blank($response->json('hosted_checkout_url'))) {
            throw new RuntimeException('SumUp: '.($response->json('message') ?? $response->json('error_message') ?? $response->status()));
        }

        $order->update(['provider_reference' => $response->json('id'), 'checkout_url' => $response->json('hosted_checkout_url')]);

        return (string) $response->json('hosted_checkout_url');
    }

    public function paidTransaction(Order $order): ?string
    {
        if (blank($order->provider_reference)) {
            return null;
        }

        $checkout = $this->client()->get('checkouts/'.$order->provider_reference)->throw()->json();
        $status = strtoupper((string) ($checkout['status'] ?? ''));

        if ($status === 'PAID') {
            $transaction = collect($checkout['transactions'] ?? [])->firstWhere('status', 'SUCCESSFUL') ?? ($checkout['transactions'][0] ?? []);

            return (string) ($transaction['id'] ?? $checkout['transaction_id'] ?? $order->provider_reference);
        }

        if ($status === 'FAILED' || $status === 'EXPIRED') {
            throw new PaymentFailed('De SumUp-betaling is mislukt of verlopen.');
        }

        return null;
    }

    public function refund(Order $order): void
    {
        if (blank($order->provider_transaction)) {
            throw new RuntimeException('Geen SumUp-transactie gevonden om terug te betalen.');
        }

        $response = $this->client()->post('me/refund/'.$order->provider_transaction);

        if ($response->failed()) {
            throw new RuntimeException('SumUp: '.($response->json('message') ?? $response->status()));
        }
    }
}
