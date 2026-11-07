<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bar takings of an evening (or a month of SumUp payments), as in the sheet "Toog Inkomsten":
 * revenue = closing cash - opening cash + card payments.
 */
class BarSession extends Model
{
    use HasFactory;

    protected $fillable = ['date', 'label', 'opening_cash', 'closing_cash', 'card_total', 'member_cards_sold', 'notes', 'user_id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'opening_cash' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'card_total' => 'decimal:2',
            'revenue' => 'decimal:2',
            'member_cards_sold' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (BarSession $session): void {
            $session->revenue = round((float) $session->closing_cash - (float) $session->opening_cash + (float) $session->card_total, 2);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
