<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoicePayment extends Model
{
    protected $fillable = ['paid_on', 'amount', 'method', 'reference', 'user_id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2', 'method' => PaymentMethod::class];
    }

    protected static function booted(): void
    {
        static::saved(fn (PurchaseInvoicePayment $payment) => $payment->purchaseInvoice->refreshPaymentStatus());
        static::deleted(fn (PurchaseInvoicePayment $payment) => $payment->purchaseInvoice->refreshPaymentStatus());
    }

    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }
}
