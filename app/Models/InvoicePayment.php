<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Support\Billit\Billit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = ['paid_on', 'amount', 'method', 'reference', 'user_id'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'amount' => 'decimal:2', 'method' => PaymentMethod::class];
    }

    protected static function booted(): void
    {
        static::saved(fn (InvoicePayment $payment) => $payment->invoice->refreshPaymentStatus());
        static::created(fn (InvoicePayment $payment) => app(Billit::class)->pushPayment($payment));
        static::deleted(fn (InvoicePayment $payment) => $payment->invoice->refreshPaymentStatus());
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
