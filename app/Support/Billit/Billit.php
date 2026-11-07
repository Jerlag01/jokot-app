<?php

namespace App\Support\Billit;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\Invoicing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Invoicing through Billit:
 * - outgoing invoices and credit notes are created in Billit with our own PDF and structured reference, and Billit
 *   delivers them (Peppol - also to Doccle users registered on Peppol through Doccle -, e-mail or letter);
 * - delivery and payment status come back by webhook or "sync";
 * - incoming invoices that arrive in Billit (e.g. over Peppol) are imported as incoming invoices.
 */
class Billit
{
    public const TRANSPORTS = [
        'auto' => 'Automatisch: Peppol als de klant erop staat, anders e-mail',
        'Peppol' => 'Peppol (ook Doccle-gebruikers met een Peppol-ID)',
        'SMTP' => 'E-mail',
        'Letter' => 'Brief (per post, via Billit)',
    ];

    /** Payments with this reference came from Billit and are not pushed back. */
    private const PAYMENT_REFERENCE = 'Billit';

    public function __construct(private BillitClient $client, private Invoicing $invoicing) {}

    public function client(): BillitClient
    {
        return $this->client;
    }

    /**
     * Looks the customer up on Peppol (by VAT/company number) and remembers the result.
     *
     * @return array{registered: bool, identifier: ?string, provider: ?string}
     */
    public function checkPeppol(Customer $customer): array
    {
        $number = preg_replace('/[^A-Za-z0-9]/', '', (string) $customer->vat_number);

        if ($number === '') {
            return ['registered' => false, 'identifier' => null, 'provider' => null];
        }

        $info = $this->client->participant($number);
        $registered = (bool) ($info['Registered'] ?? false) && in_array('BISv3Invoice', $info['DocumentTypes'] ?? [], true);

        $customer->forceFill([
            'peppol_identifier' => $registered ? ($info['Identifier'] ?? null) : null,
            'peppol_provider' => $registered ? ($info['ServiceProviderName'] ?? null) : null,
            'peppol_checked_at' => now(),
        ])->save();

        return ['registered' => $registered, 'identifier' => $customer->peppol_identifier, 'provider' => $customer->peppol_provider];
    }

    /**
     * The transport to use: "auto" picks Peppol for customers on Peppol, otherwise e-mail.
     */
    public function resolveTransport(Invoice $invoice, string $transport): string
    {
        if ($transport !== 'auto') {
            return $transport;
        }

        try {
            if ($this->checkPeppol($invoice->customer)['registered']) {
                return 'Peppol';
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return 'SMTP';
    }

    /**
     * Finalises a draft, creates the order in Billit (once) and lets Billit deliver it.
     */
    public function send(Invoice $invoice, ?string $transport = null): string
    {
        if ($invoice->isDraft()) {
            $invoice->finalize();
        }

        $invoice->loadMissing('customer', 'lines', 'creditedInvoice');
        $transport = $this->resolveTransport($invoice, $transport ?? (string) settings()->get('billit.transport', 'auto'));

        if ($transport === 'SMTP' && blank($invoice->customer->email)) {
            throw new BillitException('De klant heeft geen e-mailadres en staat niet op Peppol.');
        }

        try {
            if (! $invoice->billit_order_id) {
                $orderId = $this->client->createOrder($this->orderPayload($invoice), 'jokot-invoice-'.$invoice->id.'-'.$invoice->number);
                $invoice->forceFill(['billit_order_id' => $orderId])->saveQuietly();
            }

            $this->client->send([(int) $invoice->billit_order_id], $transport);

            $invoice->forceFill([
                'billit_transport' => $transport,
                'billit_sent_at' => now(),
                'billit_delivery_status' => 'Pending',
                'billit_recipient_provider' => $transport === 'Peppol' ? $invoice->customer->peppol_provider : null,
                'billit_error' => null,
                'sent_at' => $invoice->sent_at ?? now(),
            ])->saveQuietly();
        } catch (Throwable $exception) {
            $invoice->forceFill(['billit_error' => $exception->getMessage()])->saveQuietly();

            throw $exception;
        }

        return $transport;
    }

    /**
     * @return array<string, mixed>
     */
    public function orderPayload(Invoice $invoice): array
    {
        $customer = $invoice->customer;

        return array_filter([
            'OrderType' => $invoice->isCreditNote() ? 'CreditNote' : 'Invoice',
            'OrderDirection' => 'Income',
            'OrderNumber' => $invoice->number,
            'OrderDate' => $invoice->issue_date->toDateString(),
            'ExpiryDate' => $invoice->due_date->toDateString(),
            'OrderTitle' => $invoice->subject,
            'PaymentReference' => $invoice->isCreditNote() ? null : $invoice->structured_reference,
            'AboutInvoiceNumber' => $invoice->creditedInvoice?->number,
            'Comments' => $invoice->notes,
            'Currency' => 'EUR',
            'Customer' => array_filter([
                'Name' => $customer->name,
                'VATNumber' => filled($customer->vat_number) ? preg_replace('/[^A-Za-z0-9]/', '', $customer->vat_number) : null,
                'PartyType' => 'Customer',
                'Email' => $customer->email,
                'Contact' => $customer->contact_name,
                'Phone' => $customer->phone,
                'Addresses' => [array_filter([
                    'AddressType' => 'InvoiceAddress',
                    'Name' => $customer->name,
                    'Street' => $customer->street,
                    'StreetNumber' => $customer->house_number,
                    'Box' => $customer->box,
                    'City' => $customer->city,
                    'Zipcode' => $customer->postal_code,
                    'CountryCode' => $customer->country ?: 'BE',
                ], fn ($value): bool => filled($value))],
            ], fn ($value): bool => filled($value)),
            'OrderLines' => $invoice->lines->map(fn ($line): array => [
                'Quantity' => (float) $line->quantity,
                'UnitPriceExcl' => (float) $line->unit_price,
                'Description' => $line->description,
                'VATPercentage' => (float) $line->vat_rate,
            ])->all(),
            'OrderPDF' => [
                'FileName' => $this->invoicing->filename($invoice),
                'MimeType' => 'application/pdf',
                'FileContent' => base64_encode($this->invoicing->pdf($invoice)->output()),
            ],
        ], fn ($value): bool => filled($value));
    }

    /**
     * Delivery status and payments from Billit. A payment Billit knows about is registered here once.
     */
    public function sync(Invoice $invoice): Invoice
    {
        if (! $invoice->billit_order_id) {
            return $invoice;
        }

        $order = $this->client->order((int) $invoice->billit_order_id);
        $delivery = $order['CurrentDocumentDeliveryDetails'] ?? [];

        $invoice->forceFill([
            'billit_delivery_status' => $delivery['DocumentDeliveryStatus'] ?? $invoice->billit_delivery_status,
            'billit_delivery_info' => trim(($delivery['DocumentDeliveryInfo'] ?? '').' '.($delivery['DocumentRefusedInfo'] ?? '')) ?: $invoice->billit_delivery_info,
            'billit_synced_at' => now(),
        ])->saveQuietly();

        if (($order['Paid'] ?? false) === true && ! $invoice->isCreditNote() && $invoice->balance() > 0 && $invoice->status !== InvoiceStatus::Cancelled) {
            $invoice->payments()->create([
                'paid_on' => isset($order['PaidDate']) ? Carbon::parse($order['PaidDate'])->toDateString() : today()->toDateString(),
                'amount' => $invoice->balance(),
                'method' => PaymentMethod::Bank,
                'reference' => self::PAYMENT_REFERENCE,
            ]);
        }

        return $invoice->refresh();
    }

    /**
     * Payments registered here are passed on to Billit, so its overview (and reminders) stay right.
     */
    public function pushPayment(InvoicePayment $payment): void
    {
        $invoice = $payment->invoice;

        if (! $invoice->billit_order_id || $payment->reference === self::PAYMENT_REFERENCE || ! settings()->get('billit.push_payments') || ! $this->client->isConfigured()) {
            return;
        }

        try {
            $this->client->addPayment((int) $invoice->billit_order_id, (float) $payment->amount, $payment->paid_on->toDateString(), 'JH Jokot: '.($payment->method?->getLabel() ?? 'betaling'));
        } catch (Throwable $exception) {
            report($exception);
            $invoice->forceFill(['billit_error' => 'Betaling niet doorgegeven aan Billit: '.$exception->getMessage()])->saveQuietly();
        }
    }

    /**
     * Imports incoming (cost) invoices and credit notes from Billit that are not here yet, with their PDF.
     *
     * @return int number imported
     */
    public function importIncoming(int $maxPages = 25): int
    {
        $imported = 0;
        $next = null;

        for ($page = 0; $page < $maxPages; $page++) {
            $result = $this->client->orders($next);

            foreach ($result['Items'] ?? [] as $item) {
                if (($item['OrderDirection'] ?? null) !== 'Cost' || ! in_array($item['OrderType'] ?? null, ['Invoice', 'CreditNote'], true)) {
                    continue;
                }

                if (PurchaseInvoice::query()->where('billit_order_id', $item['OrderID'])->exists()) {
                    continue;
                }

                try {
                    $this->importOrder($this->client->order((int) $item['OrderID']));
                    $imported++;
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            $next = $result['NextPageLink'] ?? null;

            if (blank($next)) {
                break;
            }
        }

        return $imported;
    }

    /**
     * @param  array<string, mixed>  $order  a Billit order (OrderDirection "Cost")
     */
    public function importOrder(array $order): PurchaseInvoice
    {
        return DB::transaction(function () use ($order): PurchaseInvoice {
            $party = $order['Supplier'] ?? $order['CounterParty'] ?? [];
            $supplier = $this->supplierFor($party);
            $file = filled($order['OrderPDF']['FileID'] ?? null) ? $this->storeFile((string) $order['OrderPDF']['FileID']) : [null, null];

            $invoice = PurchaseInvoice::query()->create([
                'type' => ($order['OrderType'] ?? 'Invoice') === 'CreditNote' ? InvoiceType::CreditNote : InvoiceType::Invoice,
                'supplier_id' => $supplier->id,
                'expense_category_id' => $supplier->default_category_id,
                'number' => $order['OrderNumber'] ?? null,
                'issue_date' => Carbon::parse($order['OrderDate'] ?? now())->toDateString(),
                'due_date' => isset($order['ExpiryDate']) ? Carbon::parse($order['ExpiryDate'])->toDateString() : null,
                'description' => $order['OrderTitle'] ?? 'Via Billit',
                'subtotal' => round((float) ($order['TotalExcl'] ?? 0), 2),
                'vat_total' => round((float) ($order['TotalVAT'] ?? 0), 2),
                'total' => round((float) ($order['TotalIncl'] ?? 0), 2),
                'payment_reference' => $order['PaymentReference'] ?? null,
                'file_path' => $file[0],
                'file_name' => $file[1],
                'notes' => 'Geïmporteerd uit Billit (order '.$order['OrderID'].').',
            ]);

            $invoice->forceFill(['billit_order_id' => $order['OrderID']])->saveQuietly();

            if (($order['Paid'] ?? false) === true && ! $invoice->isCreditNote()) {
                $invoice->payments()->create([
                    'paid_on' => isset($order['PaidDate']) ? Carbon::parse($order['PaidDate'])->toDateString() : today()->toDateString(),
                    'amount' => $invoice->total,
                    'method' => PaymentMethod::Bank,
                    'reference' => self::PAYMENT_REFERENCE,
                ]);
            }

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $party
     */
    private function supplierFor(array $party): Supplier
    {
        $vat = filled($party['VATNumber'] ?? null) ? preg_replace('/[^A-Za-z0-9]/', '', (string) $party['VATNumber']) : null;
        $name = $party['Name'] ?? $party['DisplayName'] ?? 'Onbekende leverancier';

        $supplier = ($vat ? Supplier::query()->whereRaw("REPLACE(REPLACE(vat_number, '.', ''), ' ', '') = ?", [$vat])->first() : null)
            ?? Supplier::query()->where('name', $name)->first();

        return $supplier ?? Supplier::query()->create([
            'name' => $name,
            'vat_number' => $vat,
            'email' => $party['Email'] ?? null,
            'iban' => $party['IBAN'] ?? null,
            'bic' => $party['BIC'] ?? null,
            'street' => $party['Street'] ?? null,
            'house_number' => $party['StreetNumber'] ?? null,
            'postal_code' => $party['Zipcode'] ?? null,
            'city' => $party['City'] ?? null,
            'country' => $party['CountryCode'] ?? 'BE',
            'default_category_id' => ExpenseCategory::query()->where('name', 'Andere')->value('id'),
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string} stored path and original name
     */
    private function storeFile(string $fileId): array
    {
        $file = $this->client->file($fileId);

        if (blank($file['FileContent'] ?? null)) {
            return [null, null];
        }

        $name = $file['FileName'] ?? ($fileId.'.pdf');
        $path = 'purchase-invoices/'.now()->format('Y').'/billit-'.Str::slug(pathinfo($name, PATHINFO_FILENAME)).'-'.Str::random(6).'.'.(pathinfo($name, PATHINFO_EXTENSION) ?: 'pdf');
        Storage::disk('local')->put($path, (string) base64_decode((string) $file['FileContent'], true));

        return [$path, $name];
    }
}
