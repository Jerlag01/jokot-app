<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The life of a cup loan, from request to return.
 */
enum CupLoanStatus: string implements HasColor, HasLabel
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case PickedUp = 'picked_up';
    case Returned = 'returned';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Requested => 'Aangevraagd',
            self::Confirmed => 'Bevestigd',
            self::PickedUp => 'Opgehaald',
            self::Returned => 'Teruggebracht',
            self::Rejected => 'Geweigerd',
            self::Cancelled => 'Geannuleerd',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Requested => 'info',
            self::Confirmed => 'primary',
            self::PickedUp => 'warning',
            self::Returned => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Statuses in which the cups are reserved or out, so they count against the stock of cups.
     *
     * @return list<self>
     */
    public static function holdingCups(): array
    {
        return [self::Confirmed, self::PickedUp];
    }
}
