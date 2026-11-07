<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Support\StructuredReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * An invoice or credit note. Drafts are freely editable and have no number; finalising numbers them
 * (F2026-0001 / C2026-0001) and adds a Belgian structured reference (+++123/4567/89012+++).
 */
class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'status', 'customer_id', 'credited_invoice_id', 'source_type', 'source_id',
        'issue_date', 'due_date', 'subject', 'notes', 'sent_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'sent_at' => 'datetime',
            'reminded_at' => 'datetime',
            'billit_sent_at' => 'datetime',
            'billit_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice): void {
            $invoice->type ??= InvoiceType::Invoice;
            $invoice->status ??= InvoiceStatus::Draft;
            $invoice->issue_date ??= today();
            $invoice->due_date ??= $invoice->issue_date->copy()->addDays((int) settings()->get('invoice_payment_days', 30));
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class)->orderBy('paid_on');
    }

    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->where('type', InvoiceType::Invoice)->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid]);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->whereDate('due_date', '<', today());
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid], true) && $this->due_date->isBefore(today());
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_total, 2);
    }

    public function displayNumber(): string
    {
        return $this->number ?? 'Concept #'.$this->id;
    }

    /**
     * Recalculates subtotal, VAT and total from the lines.
     */
    public function recalculate(): void
    {
        $lines = $this->lines()->get();
        $subtotal = round((float) $lines->sum('line_total'), 2);
        $vat = round($lines->groupBy(fn (InvoiceLine $line): string => (string) (float) $line->vat_rate)
            ->sum(fn ($group, string $rate): float => round((float) $group->sum('line_total') * (float) $rate / 100, 2)), 2);

        $this->forceFill(['subtotal' => $subtotal, 'vat_total' => $vat, 'total' => round($subtotal + $vat, 2)])->saveQuietly();
    }

    /**
     * @return array<string, array{base: float, vat: float}> VAT rate => totals, for the VAT summary on the PDF
     */
    public function vatSummary(): array
    {
        return $this->lines
            ->groupBy(fn (InvoiceLine $line): string => (string) (float) $line->vat_rate)
            ->map(fn ($group, string $rate): array => [
                'base' => round((float) $group->sum('line_total'), 2),
                'vat' => round((float) $group->sum('line_total') * (float) $rate / 100, 2),
            ])
            ->sortKeys()
            ->all();
    }

    /**
     * Gives a draft its number and structured reference. Numbers are per year and per type, without gaps.
     */
    public function finalize(): void
    {
        if (! $this->isDraft()) {
            throw new RuntimeException('Enkel een concept kan definitief gemaakt worden.');
        }

        if ($this->lines()->doesntExist()) {
            throw new RuntimeException('Een factuur zonder lijnen kan niet definitief gemaakt worden.');
        }

        DB::transaction(function (): void {
            $prefix = ($this->isCreditNote() ? 'C' : 'F').$this->issue_date->format('Y').'-';

            $last = static::query()
                ->where('number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

            $this->recalculate();
            $this->forceFill([
                'number' => $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
                'structured_reference' => StructuredReference::forInvoice((int) $this->issue_date->format('Y'), $sequence, $this->isCreditNote()),
                'status' => InvoiceStatus::Open,
            ])->save();

            $this->refreshPaymentStatus();
            $this->creditedInvoice?->refreshPaymentStatus();
        });
    }

    /**
     * Status from payments: open, partially paid or paid. Drafts and cancelled invoices keep their status.
     */
    public function refreshPaymentStatus(): void
    {
        // A finalised credit note settles (part of) the invoice it credits; the credit note itself is then settled too.
        $credited = $this->isCreditNote() ? 0.0 : (float) $this->creditNotes()->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])->sum('total');
        $paid = round((float) $this->payments()->sum('amount') + $credited, 2);

        if ($this->isCreditNote() && $this->credited_invoice_id && ! in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            $paid = (float) $this->total;
        }

        $this->paid_total = $paid;

        if (! in_array($this->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            $this->status = match (true) {
                (float) $this->total <= 0 || $paid >= (float) $this->total => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::PartiallyPaid,
                default => InvoiceStatus::Open,
            };
        }

        $this->saveQuietly();
    }
}
