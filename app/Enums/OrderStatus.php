<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A ticket order. Pending orders hold their tickets until expires_at.
 */
enum OrderStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Wacht op betaling',
            self::Paid => 'Betaald',
            self::Cancelled => 'Geannuleerd',
            self::Expired => 'Verlopen',
            self::Refunded => 'Terugbetaald',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
            self::Expired => 'gray',
            self::Refunded => 'danger',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
