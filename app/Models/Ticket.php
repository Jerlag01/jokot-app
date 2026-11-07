<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One admission. The QR code holds only the code; codes are random and unguessable.
 */
class Ticket extends Model
{
    /** Without 0/O and 1/I, so codes can be typed over by hand. */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $fillable = ['order_id', 'event_id', 'ticket_type_id', 'code', 'holder_name', 'checked_in_at', 'checked_in_by', 'cancelled_at'];

    protected function casts(): array
    {
        return ['checked_in_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket): void {
            $ticket->code ??= static::newCode();
        });
    }

    public static function newCode(): string
    {
        do {
            $code = 'JK'.collect(range(1, 10))->map(fn (): string => self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)])->implode('');
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * Normalises scanned or typed input: spaces, dashes and lower case are ignored.
     */
    public static function normalizeCode(string $input): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function scopeValid(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function displayCode(): string
    {
        return implode('-', str_split($this->code, 4));
    }
}
