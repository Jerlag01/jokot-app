<?php

namespace App\Support\Billit;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the Billit REST API (https://docs.billit.be, Swagger at https://api.billit.be/swagger/docs/v1).
 * Authentication: "ApiKey" header, "PartyID" selects the company.
 */
class BillitClient
{
    public function baseUrl(): string
    {
        return settings()->get('billit.environment') === 'production' ? 'https://api.billit.be' : 'https://api.sandbox.billit.be';
    }

    public function isConfigured(): bool
    {
        return (bool) settings()->get('billit.enabled') && filled(settings()->secret('billit.api_key'));
    }

    private function request(?string $idempotencyKey = null): PendingRequest
    {
        $headers = array_filter([
            'ApiKey' => (string) settings()->secret('billit.api_key'),
            'PartyID' => filled(settings()->get('billit.party_id')) ? (string) settings()->get('billit.party_id') : null,
            'Idempotent-Key' => $idempotencyKey,
        ]);

        return Http::baseUrl($this->baseUrl())->acceptJson()->asJson()->timeout(30)->withHeaders($headers);
    }

    /**
     * @return array<string, mixed>
     */
    public function accountInformation(): array
    {
        return $this->check(Http::baseUrl($this->baseUrl())->acceptJson()->timeout(20)
            ->withHeaders(['ApiKey' => (string) settings()->secret('billit.api_key')])
            ->get('/v1/account/accountInformation'))->json() ?? [];
    }

    /**
     * Peppol participant lookup by VAT or company number (no API key needed).
     *
     * @return array{Registered?: bool, Identifier?: string, DocumentTypes?: list<string>, ServiceProviderName?: string}
     */
    public function participant(string $vatOrCbe): array
    {
        return $this->check(Http::baseUrl($this->baseUrl())->acceptJson()->timeout(20)
            ->get('/v1/peppol/participantInformation/'.rawurlencode($vatOrCbe)))->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $order
     */
    public function createOrder(array $order, string $idempotencyKey): int
    {
        return (int) $this->check($this->request($idempotencyKey)->post('/v1/orders', $order))->body();
    }

    /**
     * @param  list<int>  $orderIds
     */
    public function send(array $orderIds, string $transportType): void
    {
        $this->check($this->request()->post('/v1/orders/commands/send', ['OrderIds' => $orderIds, 'TransportType' => $transportType]));
    }

    /**
     * @return array<string, mixed>
     */
    public function order(int $orderId): array
    {
        return $this->check($this->request()->get('/v1/orders/'.$orderId))->json() ?? [];
    }

    /**
     * One page of orders; pass the NextPageLink of the previous page to continue.
     *
     * @return array{Items?: list<array<string, mixed>>, NextPageLink?: ?string}
     */
    public function orders(?string $nextPageLink = null): array
    {
        $response = $nextPageLink ? $this->request()->get($nextPageLink) : $this->request()->get('/v1/orders');

        return $this->check($response)->json() ?? [];
    }

    /**
     * @return array{FileID?: string, FileName?: string, MimeType?: string, FileContent?: string}
     */
    public function file(string $fileId): array
    {
        return $this->check($this->request()->get('/v1/files/'.rawurlencode($fileId)))->json() ?? [];
    }

    public function addPayment(int $orderId, float $amount, string $date, string $description): void
    {
        $this->check($this->request()->post('/v1/orders/'.$orderId.'/payments', ['Amount' => $amount, 'Date' => $date, 'Description' => $description]));
    }

    /**
     * @return array{WebhookID?: int, Secret?: string}
     */
    public function createWebhook(string $url): array
    {
        return $this->check($this->request()->post('/v1/webhooks', ['EntityType' => 'Order', 'WebhookURL' => $url]))->json() ?? [];
    }

    public function deleteWebhook(int $webhookId): void
    {
        $this->request()->delete('/v1/webhooks/'.$webhookId);
    }

    private function check(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $errors = collect($response->json('errors') ?? [])->map(fn (array $error): string => trim(($error['Code'] ?? '').' '.($error['Description'] ?? '')))->filter()->implode('; ');

        throw new BillitException('Billit '.$response->status().': '.($errors ?: ($response->json('Message') ?? mb_substr($response->body(), 0, 300))));
    }

    /**
     * Billit-Signature: t=<timestamp>,s=<hex HMAC-SHA256 of "t.body" with the webhook secret>.
     */
    public function verifyWebhook(string $payload, ?string $header): bool
    {
        $secret = settings()->secret('billit.webhook_secret');

        if (blank($secret) || blank($header)) {
            return false;
        }

        parse_str(str_replace(',', '&', $header), $parts);
        $timestamp = (int) ($parts['t'] ?? 0);

        if ($timestamp === 0 || abs(time() - $timestamp) > 300 || blank($parts['s'] ?? null)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), (string) $parts['s']);
    }
}
