<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A kind of ticket for an event (e.g. presale, member, at the door), with its own price and optional capacity.
 */
class TicketType extends Model
{
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'description', 'price', 'capacity', 'max_per_order', 'sales_start', 'sales_end', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'capacity' => 'integer',
            'max_per_order' => 'integer',
            'sales_start' => 'datetime',
            'sales_end' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOnSale(): bool
    {
        return $this->is_active
            && ($this->sales_start === null || $this->sales_start->isPast())
            && ($this->sales_end === null || $this->sales_end->isFuture())
            && $this->event->starts_at->isFuture();
    }

    /**
     * Sold tickets plus tickets held by pending orders that have not expired yet.
     */
    public function reservedCount(): int
    {
        return (int) OrderItem::query()
            ->where('ticket_type_id', $this->id)
            ->whereHas('order', fn ($query) => $query
                ->where('status', OrderStatus::Paid)
                ->orWhere(fn ($query) => $query->where('status', OrderStatus::Pending)->where('expires_at', '>', now())))
            ->sum('quantity');
    }

    public function remaining(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->reservedCount());
    }
}
