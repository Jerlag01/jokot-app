<?php

namespace App\Models;

use App\Enums\CardTransactionStatus;
use App\Enums\CardTransactionType;
use App\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One movement on a member card's budget. Rows written by the POS only carry member_id, amount and type.
 */
class MemberCardTransaction extends Model
{
    protected $fillable = [
        'member_id', 'amount', 'type', 'status', 'description', 'provider', 'provider_reference', 'provider_transaction',
        'checkout_url', 'email', 'token', 'ip', 'created_by', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'type' => CardTransactionType::class,
            'status' => CardTransactionStatus::class,
            'provider' => PaymentProvider::class,
            'completed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCompleted(Builder $query): void
    {
        $query->where('status', CardTransactionStatus::Completed);
    }

    public function isPending(): bool
    {
        return $this->status === CardTransactionStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === CardTransactionStatus::Completed;
    }

    public function publicUrl(): string
    {
        return route('card-topups.show', $this);
    }
}
