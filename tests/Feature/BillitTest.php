<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Support\Billit\Billit;
use App\Support\Billit\BillitException;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BillitTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.sandbox.billit.be';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        app(Settings::class)->save(['billit' => [
            ...Settings::defaults()['billit'],
            'enabled' => true,
            'environment' => 'sandbox',
            'api_key' => Crypt::encryptString('test-key'),
            'party_id' => '12345',
            'webhook_secret' => Crypt::encryptString('whsec'),
        ]]);
    }

    private function invoice(array $customer = []): Invoice
    {
        return Invoice::factory()
            ->withLines([['Huur zaal', 1, 250, 0], ['Drank', 10, 1.5, 21]])
            ->create(['customer_id' => Customer::factory()->create(['name' => 'Chiro Niel', 'vat_number' => 'BE 0123.456.789', 'email' => 'kas@chiro.be', ...$customer])->id]);
    }

    public function test_an_invoice_is_created_in_billit_and_sent_over_peppol_when_the_customer_is_on_peppol(): void
    {
        Http::fake([
            self::API.'/v1/peppol/participantInformation/*' => Http::response(['Registered' => true, 'Identifier' => '0208:0123456789', 'DocumentTypes' => ['BISv3Invoice', 'BISv3CreditNote'], 'ServiceProviderName' => 'Doccle']),
            self::API.'/v1/orders/commands/send' => Http::response('', 200),
            self::API.'/v1/orders' => Http::response('987654', 200),
        ]);
        $invoice = $this->invoice();

        $this->assertSame('Peppol', app(Billit::class)->send($invoice, 'auto'));

        $invoice->refresh();
        $this->assertNotNull($invoice->number);
        $this->assertSame(987654, (int) $invoice->billit_order_id);
        $this->assertSame('Peppol', $invoice->billit_transport);
        $this->assertSame('Doccle', $invoice->billit_recipient_provider);
        $this->assertSame('Doccle', $invoice->customer->fresh()->peppol_provider);

        Http::assertSent(function (HttpRequest $request) use ($invoice): bool {
            if ($request->url() !== self::API.'/v1/orders') {
                return false;
            }

            return $request->hasHeader('ApiKey', 'test-key') && $request->hasHeader('PartyID', '12345')
                && $request['OrderType'] === 'Invoice' && $request['OrderDirection'] === 'Income'
                && $request['OrderNumber'] === $invoice->number && $request['PaymentReference'] === $invoice->structured_reference
                && $request['Customer']['VATNumber'] === 'BE0123456789'
                && $request['OrderLines'][1] == ['Quantity' => 10.0, 'UnitPriceExcl' => 1.5, 'Description' => 'Drank', 'VATPercentage' => 21.0]
                && str_starts_with(base64_decode($request['OrderPDF']['FileContent']), '%PDF');
        });
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::API.'/v1/orders/commands/send' && $request['OrderIds'] === [987654] && $request['TransportType'] === 'Peppol');
    }

    public function test_customers_without_peppol_get_it_by_e_mail_and_the_order_is_created_only_once(): void
    {
        Http::fake([
            self::API.'/v1/peppol/participantInformation/*' => Http::response(['Registered' => false]),
            self::API.'/v1/orders/commands/send' => Http::response('', 200),
            self::API.'/v1/orders' => Http::response('55', 200),
        ]);
        $invoice = $this->invoice();

        $this->assertSame('SMTP', app(Billit::class)->send($invoice, 'auto'));
        app(Billit::class)->send($invoice->fresh(), 'SMTP');

        Http::assertSentCount(4);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::API.'/v1/orders' && $request->hasHeader('Idempotent-Key'));
    }

    public function test_billit_errors_are_stored_and_shown(): void
    {
        Http::fake([self::API.'/v1/orders' => Http::response(['errors' => [['Code' => 'TheCustomerIsNotActiveOnPeppol', 'Description' => 'Not on Peppol']]], 400)]);
        $invoice = $this->invoice();

        try {
            app(Billit::class)->send($invoice, 'Peppol');
            $this->fail('An exception was expected.');
        } catch (BillitException $exception) {
            $this->assertStringContainsString('TheCustomerIsNotActiveOnPeppol', $exception->getMessage());
        }

        $this->assertStringContainsString('TheCustomerIsNotActiveOnPeppol', $invoice->fresh()->billit_error);
    }

    public function test_sync_takes_over_delivery_and_payment(): void
    {
        Http::fake([self::API.'/v1/orders/77' => Http::response([
            'OrderID' => 77, 'Paid' => true, 'PaidDate' => '2026-11-20T00:00:00',
            'CurrentDocumentDeliveryDetails' => ['DocumentDeliveryStatus' => 'Delivered', 'DocumentDeliveryInfo' => 'Received'],
        ])]);
        $invoice = $this->invoice();
        $invoice->finalize();
        $invoice->forceFill(['billit_order_id' => 77])->save();

        app(Billit::class)->sync($invoice);
        app(Billit::class)->sync($invoice->fresh());

        $invoice->refresh();
        $this->assertSame('Delivered', $invoice->billit_delivery_status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(1, $invoice->payments()->count());
        $this->assertSame('2026-11-20', $invoice->payments()->first()->paid_on->toDateString());
        // A payment that came from Billit is not sent back to Billit.
        Http::assertNotSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/payments'));
    }

    public function test_payments_registered_here_are_passed_to_billit(): void
    {
        Http::fake([self::API.'/v1/orders/88/payments' => Http::response('', 200)]);
        $invoice = $this->invoice();
        $invoice->finalize();
        $invoice->forceFill(['billit_order_id' => 88])->save();

        $invoice->payments()->create(['paid_on' => '2026-11-21', 'amount' => 100, 'method' => PaymentMethod::Cash]);

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === self::API.'/v1/orders/88/payments' && $request['Amount'] == 100 && $request['Date'] === '2026-11-21');
    }

    public function test_the_webhook_checks_the_signature_and_syncs_the_invoice(): void
    {
        Http::fake([self::API.'/v1/orders/77' => Http::response(['OrderID' => 77, 'Paid' => false, 'CurrentDocumentDeliveryDetails' => ['DocumentDeliveryStatus' => 'Delivered']])]);
        $invoice = $this->invoice();
        $invoice->finalize();
        $invoice->forceFill(['billit_order_id' => 77])->save();
        $payload = json_encode(['OrderID' => 77, 'EntityType' => 'Order']);

        $this->call('POST', route('webhooks.billit'), [], [], [], ['HTTP_BILLIT_SIGNATURE' => 't='.time().',s=forged', 'CONTENT_TYPE' => 'application/json'], $payload)->assertStatus(400);

        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec');
        $this->call('POST', route('webhooks.billit'), [], [], [], ['HTTP_BILLIT_SIGNATURE' => "t={$timestamp},s={$signature}", 'CONTENT_TYPE' => 'application/json'], $payload)->assertOk();

        $this->assertSame('Delivered', $invoice->fresh()->billit_delivery_status);
    }

    public function test_incoming_invoices_are_imported_with_their_pdf_once(): void
    {
        $costOrder = [
            'OrderID' => 501, 'OrderType' => 'Invoice', 'OrderDirection' => 'Cost', 'OrderNumber' => 'BR-2026-118',
            'OrderDate' => '2026-11-02T00:00:00', 'ExpiryDate' => '2026-12-02T00:00:00', 'TotalExcl' => 300.0, 'TotalVAT' => 63.0, 'TotalIncl' => 363.0,
            'PaymentReference' => '+++123/4567/89002+++', 'Paid' => false,
            'Supplier' => ['Name' => 'Brouwerij Het Anker', 'VATNumber' => 'BE0400.000.001', 'IBAN' => 'BE71096123456769'],
            'OrderPDF' => ['FileID' => '0f8fad5b-d9cb-469f-a165-70867728950e', 'FileName' => 'factuur.pdf'],
        ];
        Http::fake([
            self::API.'/v1/orders' => Http::response(['Items' => [$costOrder, ['OrderID' => 502, 'OrderType' => 'Invoice', 'OrderDirection' => 'Income']], 'NextPageLink' => null]),
            self::API.'/v1/orders/501' => Http::response($costOrder),
            self::API.'/v1/files/*' => Http::response(['FileName' => 'factuur.pdf', 'MimeType' => 'application/pdf', 'FileContent' => base64_encode('%PDF-1.4 brouwerij')]),
        ]);

        $this->assertSame(1, app(Billit::class)->importIncoming());
        $this->assertSame(0, app(Billit::class)->importIncoming());

        $purchase = PurchaseInvoice::query()->sole();
        $this->assertSame('363.00', $purchase->total);
        $this->assertSame('BR-2026-118', $purchase->number);
        $this->assertSame(InvoiceType::Invoice, $purchase->type);
        $this->assertSame('Brouwerij Het Anker', $purchase->supplier->name);
        $this->assertSame('BE71096123456769', $purchase->supplier->iban);
        $this->assertSame('%PDF-1.4 brouwerij', Storage::disk('local')->get($purchase->file_path));
    }

    public function test_the_admin_action_sends_through_billit(): void
    {
        Http::fake([
            self::API.'/v1/orders/commands/send' => Http::response('', 200),
            self::API.'/v1/orders' => Http::response('42', 200),
        ]);
        $this->actingAs(User::factory()->admin()->create());
        $invoice = $this->invoice();

        Livewire::test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('billitSend')
            ->callAction('billitSend', data: ['transport' => 'SMTP'])
            ->assertNotified('Via Billit verstuurd (SMTP)');

        $this->assertSame(42, (int) $invoice->fresh()->billit_order_id);
    }

    public function test_nothing_is_offered_when_billit_is_off(): void
    {
        app(Settings::class)->save(['billit' => [...Settings::defaults()['billit'], 'enabled' => false]]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ViewInvoice::class, ['record' => $this->invoice()->getRouteKey()])->assertActionHidden('billitSend');
    }
}
