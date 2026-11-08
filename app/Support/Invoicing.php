<?php

namespace App\Support;

use App\Enums\InvoiceType;
use App\Enums\StockMovementType;
use App\Mail\InvoiceMail;
use App\Models\CupLoan;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RentalRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/**
 * Invoices like Billit: drafts built from rentals and cup loans, credit notes, PDF with payment QR, e-mail.
 */
class Invoicing
{
    /**
     * @param  list<array{description: string, quantity: float|int, unit_price: float|int|string, vat_rate?: float|int, product_id?: int|null}>  $lines
     */
    public function createDraft(Customer $customer, array $lines, ?Model $source = null, ?string $subject = null, InvoiceType $type = InvoiceType::Invoice): Invoice
    {
        return DB::transaction(function () use ($customer, $lines, $source, $subject, $type): Invoice {
            $invoice = Invoice::query()->create([
                'type' => $type,
                'customer_id' => $customer->id,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'subject' => $subject,
            ]);

            foreach (array_values($lines) as $sort => $line) {
                $invoice->lines()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'vat_rate' => $line['vat_rate'] ?? (float) settings()->get('default_vat_rate', 0),
                    'sort' => $sort,
                ]);
            }

            $invoice->recalculate();

            return $invoice;
        });
    }

    /**
     * Hall rent, DJ set, the drinks consumed (count before minus after, at the renter price), corkage,
     * minus the deposit already paid - as in the contract. Books the consumed drinks out of stock once.
     */
    public function forRental(RentalRequest $rental): Invoice
    {
        if ($rental->invoice_id) {
            throw new RuntimeException('Voor deze verhuur bestaat al een factuur.');
        }

        $rental->loadMissing('rentalRate', 'rentalExtra', 'drinkCounts.product');
        $lines = [];

        if ($rental->rentalRate) {
            $lines[] = ['description' => 'Huur zaal '.$rental->event_date->format('d/m/Y').' - '.$rental->rentalRate->name, 'quantity' => 1, 'unit_price' => $rental->rentalRate->price];
        }

        if ($rental->rentalExtra) {
            $lines[] = ['description' => $rental->rentalExtra->name, 'quantity' => 1, 'unit_price' => $rental->rentalExtra->price];
        }

        foreach ($rental->drinkCounts as $count) {
            if ($count->consumed() > 0) {
                $lines[] = [
                    'product_id' => $count->product_id,
                    'description' => $count->product->name,
                    'quantity' => $count->consumed(),
                    'unit_price' => $count->product->rental_price ?? $count->product->bar_price ?? 0,
                ];
            }
        }

        if ($rental->corkage_bottles > 0) {
            $lines[] = ['description' => 'Kurkgeld (per geopende fles van 75cl)', 'quantity' => $rental->corkage_bottles, 'unit_price' => settings()->get('corkage_fee', 3)];
        }

        if ($rental->deposit_paid_at && (float) $rental->quoted_deposit > 0) {
            $lines[] = ['description' => 'Betaalde waarborg ('.$rental->deposit_paid_at->format('d/m/Y').')', 'quantity' => 1, 'unit_price' => -1 * (float) $rental->quoted_deposit, 'vat_rate' => 0];
        }

        return DB::transaction(function () use ($rental, $lines): Invoice {
            $invoice = $this->createDraft($rental->ensureCustomer(), $lines, $rental, 'Verhuur JH Jokot '.$rental->event_date->format('d/m/Y').' ('.$rental->reference.')');
            $rental->update(['invoice_id' => $invoice->id]);
            $this->bookRentalDrinks($rental);

            return $invoice;
        });
    }

    public function bookRentalDrinks(RentalRequest $rental): void
    {
        if ($rental->drinks_booked_at) {
            return;
        }

        foreach ($rental->drinkCounts()->with('product')->get() as $count) {
            if ($count->consumed() > 0 && $count->product->track_stock) {
                app(Stock::class)->move($count->product, StockMovementType::Rental, -$count->consumed(), 'Verhuur '.$rental->reference, $rental);
            }
        }

        $rental->update(['drinks_booked_at' => now()]);
    }

    /**
     * The fixed fee, plus missing or dirty cups and missing or damaged crates (art. 7 and 9 of the contract).
     */
    public function forCupLoan(CupLoan $loan): Invoice
    {
        if ($loan->invoice_id) {
            throw new RuntimeException('Voor deze uitlening bestaat al een factuur.');
        }

        $lines = [['description' => "Huur wisselbekers ({$loan->cups} bekers, {$loan->starts_on->format('d/m/Y')} - {$loan->ends_on->format('d/m/Y')})", 'quantity' => 1, 'unit_price' => $loan->rental_fee]];

        if ($loan->cupsMissing() > 0) {
            $lines[] = ['description' => 'Ontbrekende bekers', 'quantity' => $loan->cupsMissing(), 'unit_price' => $loan->cup_fee];
        }

        if ($loan->cups_dirty > 0) {
            $lines[] = ['description' => 'Vuile bekers', 'quantity' => $loan->cups_dirty, 'unit_price' => $loan->cup_fee];
        }

        if ($loan->cratesCharged() > 0) {
            $lines[] = ['description' => 'Ontbrekende of beschadigde bakken', 'quantity' => $loan->cratesCharged(), 'unit_price' => $loan->crate_fee];
        }

        return DB::transaction(function () use ($loan, $lines): Invoice {
            $invoice = $this->createDraft($loan->ensureCustomer(), $lines, $loan, 'Wisselbekers '.$loan->reference.($loan->event_name ? ' - '.$loan->event_name : ''));
            $loan->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    public function creditNoteFor(Invoice $invoice): Invoice
    {
        if ($invoice->isDraft() || $invoice->isCreditNote()) {
            throw new RuntimeException('Enkel een definitieve factuur kan gecrediteerd worden.');
        }

        $creditNote = $this->createDraft(
            $invoice->customer,
            $invoice->lines->map(fn ($line): array => [
                'product_id' => $line->product_id,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'vat_rate' => (float) $line->vat_rate,
            ])->all(),
            subject: 'Creditnota voor factuur '.$invoice->number,
            type: InvoiceType::CreditNote,
        );

        $creditNote->update(['credited_invoice_id' => $invoice->id]);

        return $creditNote;
    }

    public function pdf(Invoice $invoice): DomPdf
    {
        $invoice->loadMissing('customer', 'lines', 'creditedInvoice');
        $settings = settings();

        $qr = (! $invoice->isCreditNote() && $invoice->balance() > 0 && filled($settings->get('iban')))
            ? Qr::pngDataUri(Qr::epcPayload((string) $settings->get('legal_name'), (string) $settings->get('iban'), $settings->get('bic'), $invoice->balance(), (string) ($invoice->structured_reference ?? $invoice->displayNumber())), 4)
            : null;

        return Pdf::loadView('pdf.invoice', ['invoice' => $invoice, 'settings' => $settings, 'paymentQr' => $qr])->setPaper('a4');
    }

    public function filename(Invoice $invoice): string
    {
        return ($invoice->isCreditNote() ? 'creditnota-' : 'factuur-').str_replace('/', '-', $invoice->displayNumber()).'.pdf';
    }

    /**
     * Mails the PDF to the customer. A draft is finalised first, so a mailed invoice always has a number.
     */
    public function send(Invoice $invoice, ?string $to = null, bool $reminder = false): void
    {
        Mailing::ensureEnabled();

        if ($invoice->isDraft()) {
            $invoice->finalize();
        }

        $to ??= $invoice->customer->email;

        if (blank($to)) {
            throw new RuntimeException('Deze klant heeft geen e-mailadres.');
        }

        Mail::to($to)->send(new InvoiceMail($invoice, $reminder));

        $invoice->forceFill($reminder ? ['reminded_at' => now()] : ['sent_at' => now()])->saveQuietly();
    }
}
