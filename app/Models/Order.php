<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A ticket order. The public order page is reached with the unguessable token, never the id.
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'status', 'name', 'email', 'phone', 'total', 'provider', 'provider_reference', 'provider_transaction',
        'checkout_url', 'expires_at', 'paid_at', 'cancelled_at', 'refunded_at', 'ip_address', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'provider' => PaymentProvider::class,
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            $order->token ??= Str::random(40);
            $order->status ??= OrderStatus::Pending;

            $prefix = 'T'.now()->format('Y').'-';
            $last = DB::table('orders')->where('number', 'like', $prefix.'%')->orderByDesc('number')->lockForUpdate()->value('number');
            $order->number ??= $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 5, '0', STR_PAD_LEFT);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPaid(): bool
    {
        return $this->status === OrderStatus::Paid;
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }

    public function ticketCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    public function publicUrl(): string
    {
        return route('orders.show', $this);
    }
}
