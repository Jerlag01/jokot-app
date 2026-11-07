<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * An incoming invoice (or credit note) from a supplier. Gets an internal booking number (A2026-0001) on creation;
 * "number" is the supplier's own invoice number. The scanned file lives on the private disk.
 */
class PurchaseInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'status', 'supplier_id', 'expense_category_id', 'event_id', 'number', 'issue_date', 'due_date', 'description',
        'subtotal', 'vat_total', 'total', 'payment_reference', 'file_path', 'file_name', 'notes', 'user_id',
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
            'stock_booked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseInvoice $invoice): void {
            $invoice->type ??= InvoiceType::Invoice;
            $invoice->status ??= InvoiceStatus::Open;

            $prefix = 'A'.($invoice->issue_date ?? now())->format('Y').'-';
            $last = DB::table('purchase_invoices')->where('reference', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('reference')->value('reference');
            $invoice->reference ??= $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 4, '0', STR_PAD_LEFT);
        });

        static::saving(function (PurchaseInvoice $invoice): void {
            // Enter either the total or the parts: missing amounts are worked out.
            if ((float) $invoice->total === 0.0 && (float) $invoice->subtotal !== 0.0) {
                $invoice->total = round((float) $invoice->subtotal + (float) $invoice->vat_total, 2);
            } elseif ((float) $invoice->subtotal === 0.0 && (float) $invoice->total !== 0.0) {
                $invoice->subtotal = round((float) $invoice->total - (float) $invoice->vat_total, 2);
            }
        });

        static::saved(function (PurchaseInvoice $invoice): void {
            if ($invoice->wasChanged('total')) {
                $invoice->refreshPaymentStatus();
            }
        });

        static::deleted(function (PurchaseInvoice $invoice): void {
            if ($invoice->file_path) {
                Storage::disk('local')->delete($invoice->file_path);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchaseInvoicePayment::class)->orderBy('paid_on');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function scopeOutstanding(Builder $query): void
    {
        $query->where('type', InvoiceType::Invoice)->whereIn('status', [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid]);
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->whereNotNull('due_date')->whereDate('due_date', '<', today());
    }

    public function isCreditNote(): bool
    {
        return $this->type === InvoiceType::CreditNote;
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [InvoiceStatus::Open, InvoiceStatus::PartiallyPaid], true) && $this->due_date?->isBefore(today());
    }

    public function balance(): float
    {
        return round((float) $this->total - (float) $this->paid_total, 2);
    }

    /**
     * Possible duplicates: same supplier and invoice number.
     */
    public function duplicates(): Builder
    {
        return static::query()->where('supplier_id', $this->supplier_id)->where('number', $this->number)->whereKeyNot($this->id);
    }

    public function refreshPaymentStatus(): void
    {
        $paid = round((float) $this->payments()->sum('amount'), 2);
        $this->paid_total = $paid;

        if ($this->status !== InvoiceStatus::Cancelled) {
            $this->status = match (true) {
                $this->isCreditNote() => $paid >= (float) $this->total ? InvoiceStatus::Paid : InvoiceStatus::Open,
                (float) $this->total <= 0 || $paid >= (float) $this->total => InvoiceStatus::Paid,
                $paid > 0 => InvoiceStatus::PartiallyPaid,
                default => InvoiceStatus::Open,
            };
        }

        $this->saveQuietly();
    }
}
