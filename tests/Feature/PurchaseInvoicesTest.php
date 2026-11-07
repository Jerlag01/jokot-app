<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\StockMovementType;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Widgets\PurchaseStats;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Qr;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_an_incoming_invoice_is_booked_with_its_scan_and_a_booking_number(): void
    {
        $supplier = Supplier::factory()->create(['payment_days' => 14, 'default_category_id' => ExpenseCategory::query()->create(['name' => 'Drank'])->id]);

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm([
                'supplier_id' => $supplier->id,
                'number' => 'VF-2026-118',
                'issue_date' => '2026-11-02',
                'subtotal' => 300,
                'vat_total' => 63,
                'file_path' => UploadedFile::fake()->create('factuur-brouwerij.pdf', 120, 'application/pdf'),
            ])
            ->assertSchemaStateSet(['due_date' => '2026-11-16', 'expense_category_id' => $supplier->default_category_id, 'total' => 363.0])
            ->call('create')
            ->assertHasNoFormErrors();

        $invoice = PurchaseInvoice::query()->sole();
        $this->assertSame('A2026-0001', $invoice->reference);
        $this->assertSame('363.00', $invoice->total);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame('factuur-brouwerij.pdf', $invoice->file_name);
        Storage::disk('local')->assertExists($invoice->file_path);
    }

    public function test_the_same_supplier_invoice_cannot_be_booked_twice(): void
    {
        $existing = PurchaseInvoice::factory()->create(['number' => 'X-1']);

        Livewire::test(CreatePurchaseInvoice::class)
            ->fillForm(['supplier_id' => $existing->supplier_id, 'number' => 'X-1', 'issue_date' => today()->toDateString(), 'total' => 50])
            ->call('create')
            ->assertHasFormErrors(['number' => 'unique']);
    }

    public function test_the_scan_is_only_served_to_users_with_the_permission(): void
    {
        Storage::disk('local')->put('purchase-invoices/2026/scan.pdf', '%PDF-1.4 test');
        $invoice = PurchaseInvoice::factory()->create(['file_path' => 'purchase-invoices/2026/scan.pdf', 'file_name' => 'scan.pdf']);

        $this->get(route('purchase-invoices.file', $invoice))->assertOk()->assertHeader('Content-Disposition', 'inline; filename="scan.pdf"');

        $this->actingAs(User::factory()->withPermissions([Permissions::INVOICES])->create());
        $this->get(route('purchase-invoices.file', $invoice))->assertForbidden();

        auth()->logout();
        $this->get(route('purchase-invoices.file', $invoice))->assertForbidden();
        $this->assertContains($this->get('/storage/purchase-invoices/2026/scan.pdf')->status(), [403, 404]);
    }

    public function test_payments_settle_the_invoice_and_overdue_ones_are_flagged(): void
    {
        $invoice = PurchaseInvoice::factory()->create(['total' => 121, 'due_date' => today()->subDay()]);
        $this->assertTrue($invoice->isOverdue());
        $this->assertSame([$invoice->id], PurchaseInvoice::query()->overdue()->pluck('id')->all());

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('registerPayment', data: ['paid_on' => today()->toDateString(), 'amount' => 121, 'method' => 'bank']);

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertFalse($invoice->fresh()->isOverdue());
    }

    public function test_a_delivery_is_booked_into_stock_per_crate(): void
    {
        $pils = Product::factory()->create(['name' => 'Pils 25cl', 'units_per_package' => 24, 'stock_quantity' => 10, 'track_stock' => true]);
        $invoice = PurchaseInvoice::factory()->create();

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $invoice->getRouteKey()])
            ->callAction('bookStock', data: ['items' => [['product_id' => $pils->id, 'packages' => 5, 'units' => 0, 'unit_price' => 0.62]]])
            ->assertHasNoActionErrors();

        $this->assertSame(130, $pils->fresh()->stock_quantity);
        $this->assertSame('0.62', $pils->fresh()->purchase_price);
        $this->assertSame(StockMovementType::Purchase, $invoice->stockMovements()->sole()->type);
        $this->assertNotNull($invoice->fresh()->stock_booked_at);
    }

    public function test_the_payment_qr_needs_the_supplier_iban(): void
    {
        $withIban = PurchaseInvoice::factory()->create();
        $withoutIban = PurchaseInvoice::factory()->create(['supplier_id' => Supplier::factory()->create(['iban' => null])->id]);

        Livewire::test(ViewPurchaseInvoice::class, ['record' => $withIban->getRouteKey()])->assertActionVisible('payQr');
        $payload = Qr::epcPayload($withIban->supplier->name, $withIban->supplier->iban, $withIban->supplier->bic, 121.0, '+++090/9337/55493+++');
        $this->assertStringContainsString("BE71096123456769\nEUR121.00\n\n090933755493", $payload);
        Livewire::test(ViewPurchaseInvoice::class, ['record' => $withoutIban->getRouteKey()])->assertActionHidden('payQr');
    }

    public function test_credit_notes_count_negative_in_the_costs(): void
    {
        PurchaseInvoice::factory()->create(['total' => 200]);
        PurchaseInvoice::factory()->create(['total' => 50, 'type' => InvoiceType::CreditNote]);

        $this->get('/admin/purchase-invoices?tab=all')->assertOk()->assertSee('150,00');
        Livewire::test(PurchaseStats::class)->assertSee('Te betalen')->assertSee('€150');
    }

    public function test_only_the_purchases_permission_opens_the_screens(): void
    {
        $invoice = PurchaseInvoice::factory()->create();

        foreach (['/admin/purchase-invoices', '/admin/purchase-invoices/create', "/admin/purchase-invoices/{$invoice->id}", "/admin/purchase-invoices/{$invoice->id}/edit", '/admin/suppliers', '/admin/expense-categories'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs(User::factory()->withPermissions([Permissions::EVENTS])->create());
        $this->get('/admin/purchase-invoices')->assertForbidden();
        $this->get('/admin/suppliers')->assertForbidden();
    }
}
