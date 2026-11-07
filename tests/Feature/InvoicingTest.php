<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\RentalStatus;
use App\Enums\StockMovementType;
use App\Mail\InvoiceMail;
use App\Models\CupLoan;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\RentalExtra;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Support\Invoicing;
use App\Support\StructuredReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_with_mixed_vat_rates(): void
    {
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 250, 0], ['Drank', 10, 1.5, 21]])->create();

        $this->assertSame('265.00', $invoice->subtotal);
        $this->assertSame('3.15', $invoice->vat_total);
        $this->assertSame('268.15', $invoice->total);
    }

    public function test_finalising_numbers_invoices_per_year_with_a_valid_structured_reference(): void
    {
        $first = Invoice::factory()->withLines([['A', 1, 10]])->create();
        $second = Invoice::factory()->withLines([['B', 1, 10]])->create();
        $draft = Invoice::factory()->withLines([['C', 1, 10]])->create();

        $first->finalize();
        $second->finalize();

        $year = now()->year;
        $this->assertSame("F{$year}-0001", $first->number);
        $this->assertSame("F{$year}-0002", $second->number);
        $this->assertNull($draft->fresh()->number);
        $this->assertTrue(StructuredReference::isValid($first->structured_reference));
        $this->assertMatchesRegularExpression('#^\+\+\+\d{3}/\d{4}/\d{5}\+\+\+$#', $first->structured_reference);
        $this->assertSame(InvoiceStatus::Open, $first->status);
    }

    public function test_an_empty_invoice_cannot_be_finalised(): void
    {
        $this->expectException(RuntimeException::class);

        Invoice::factory()->create()->finalize();
    }

    public function test_payments_move_the_status_to_partially_paid_and_paid(): void
    {
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 100]])->create();
        $invoice->finalize();

        $invoice->payments()->create(['paid_on' => today(), 'amount' => 40, 'method' => PaymentMethod::Bank]);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->fresh()->status);
        $this->assertSame(60.0, $invoice->fresh()->balance());

        $invoice->payments()->create(['paid_on' => today(), 'amount' => 60, 'method' => PaymentMethod::Cash]);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_a_finalised_credit_note_settles_the_invoice(): void
    {
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 150]])->create();
        $invoice->finalize();

        $creditNote = app(Invoicing::class)->creditNoteFor($invoice);
        $creditNote->finalize();

        $this->assertSame('C'.now()->year.'-0001', $creditNote->number);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(InvoiceStatus::Paid, $creditNote->fresh()->status);
    }

    public function test_overdue_invoices(): void
    {
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 150]])->create(['issue_date' => today()->subDays(40), 'due_date' => today()->subDays(10)]);
        $invoice->finalize();

        $this->assertTrue($invoice->fresh()->isOverdue());
        $this->assertSame([$invoice->id], Invoice::query()->overdue()->pluck('id')->all());
    }

    public function test_sending_finalises_and_attaches_the_pdf(): void
    {
        Mail::fake();
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 150]])->create();

        app(Invoicing::class)->send($invoice);

        $this->assertNotNull($invoice->fresh()->number);
        $this->assertNotNull($invoice->fresh()->sent_at);
        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail): bool => $mail->hasTo($invoice->customer->email) && count($mail->attachments()) === 1);
    }

    public function test_the_pdf_renders(): void
    {
        $invoice = Invoice::factory()->withLines([['Zaal', 1, 150, 21]])->create();
        $invoice->finalize();

        $this->assertStringStartsWith('%PDF', app(Invoicing::class)->pdf($invoice)->output());
    }

    public function test_a_rental_invoice_has_rent_extras_consumption_corkage_and_deposit_and_books_stock_once(): void
    {
        $rental = RentalRequest::factory()->create([
            'status' => RentalStatus::Confirmed,
            'rental_rate_id' => RentalRate::factory()->create(['name' => 'Privaat/Bedrijf', 'price' => 400, 'deposit' => 200])->id,
            'rental_extra_id' => RentalExtra::factory()->create(['name' => 'Nexus 2', 'price' => 225, 'deposit' => 75])->id,
            'corkage_bottles' => 4,
        ]);
        $rental->update(['deposit_paid_at' => now()]);
        $pils = Product::factory()->create(['name' => 'Pils 25cl', 'rental_price' => 1.8, 'stock_quantity' => 200]);
        $keg = Product::factory()->create(['name' => 'Vat 50L', 'rental_price' => 220, 'stock_quantity' => 3, 'units_per_package' => null]);
        $rental->drinkCounts()->create(['product_id' => $pils->id, 'quantity_before' => 120, 'quantity_after' => 70]);
        $rental->drinkCounts()->create(['product_id' => $keg->id, 'quantity_before' => 2, 'quantity_after' => 1]);

        $invoice = app(Invoicing::class)->forRental($rental);

        $this->assertSame([
            ['Huur zaal '.$rental->event_date->format('d/m/Y').' - Privaat/Bedrijf', '1.00', '400.00'],
            ['Nexus 2', '1.00', '225.00'],
            ['Pils 25cl', '50.00', '1.80'],
            ['Vat 50L', '1.00', '220.00'],
            ['Kurkgeld (per geopende fles van 75cl)', '4.00', '3.00'],
            ['Betaalde waarborg ('.now()->format('d/m/Y').')', '1.00', '-275.00'],
        ], $invoice->lines->map(fn ($line) => [$line->description, $line->quantity, $line->unit_price])->all());
        $this->assertSame('672.00', $invoice->total);
        $this->assertSame($invoice->id, $rental->fresh()->invoice_id);
        $this->assertSame(150, $pils->fresh()->stock_quantity);
        $this->assertSame(StockMovementType::Rental, $pils->stockMovements()->sole()->type);

        app(Invoicing::class)->bookRentalDrinks($rental->fresh());
        $this->assertSame(150, $pils->fresh()->stock_quantity);

        $this->expectException(RuntimeException::class);
        app(Invoicing::class)->forRental($rental->fresh());
    }

    public function test_a_cup_loan_invoice_charges_missing_dirty_cups_and_crates(): void
    {
        $loan = CupLoan::factory()->create(['cups' => 300, 'crates' => 6, 'rental_fee' => 15, 'cup_fee' => 0.25, 'crate_fee' => 25]);
        $loan->update(['cups_returned' => 290, 'cups_dirty' => 12, 'crates_returned' => 6, 'crates_damaged' => 1]);

        $invoice = app(Invoicing::class)->forCupLoan($loan->fresh());

        $this->assertSame('15.00', $invoice->lines[0]->unit_price);
        $this->assertSame(['10.00', '12.00', '1.00'], $invoice->lines->slice(1)->pluck('quantity')->values()->all());
        $this->assertSame('45.50', $invoice->total);
    }
}
