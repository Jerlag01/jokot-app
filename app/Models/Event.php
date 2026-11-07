<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * An activity of the youth centre itself. By default it blocks its date for rentals ("voorrang eigen werking").
 */
class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'kind', 'slug', 'starts_at', 'ends_at', 'summary', 'description', 'image', 'ticket_url', 'price_label',
        'is_published', 'blocks_rentals', 'tickets_enabled', 'capacity', 'ticket_info',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'blocks_rentals' => 'boolean',
            'tickets_enabled' => 'boolean',
            'capacity' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Event $event): void {
            if (blank($event->slug)) {
                $base = Str::slug($event->title.' '.$event->starts_at?->format('Y-m-d'));
                $slug = $base;
                $counter = 2;

                while (static::query()->where('slug', $slug)->whereKeyNot($event->getKey())->exists()) {
                    $slug = $base.'-'.$counter++;
                }

                $event->slug = $slug;
            }
        });
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class)->orderBy('sort')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Online ticket sales are open: switched on, in the future, and at least one type on sale.
     */
    public function sellsTickets(): bool
    {
        return $this->tickets_enabled && $this->is_published && $this->starts_at->isFuture()
            && $this->ticketTypes->contains(fn (TicketType $type): bool => $type->isOnSale());
    }

    /**
     * Tickets still available for the whole event (null = no event-wide limit).
     */
    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        $reserved = (int) OrderItem::query()
            ->whereHas('order', fn ($query) => $query->where('event_id', $this->id)->where(fn ($query) => $query
                ->where('status', OrderStatus::Paid)
                ->orWhere(fn ($query) => $query->where('status', OrderStatus::Pending)->where('expires_at', '>', now()))))
            ->sum('quantity');

        return max(0, $this->capacity - $reserved);
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->where('starts_at', '>=', now()->startOfDay())
            ->orWhere('ends_at', '>=', now()))
            ->orderBy('starts_at');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
