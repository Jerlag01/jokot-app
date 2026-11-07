<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Where a rental request stands. Option and confirmed both mark the date as taken on the public calendar.
 */
enum RentalStatus: string implements HasColor, HasLabel
{
    case New = 'new';
    case Option = 'option';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nieuw',
            self::Option => 'In optie',
            self::Confirmed => 'Bevestigd',
            self::Rejected => 'Geweigerd',
            self::Cancelled => 'Geannuleerd',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Option => 'warning',
            self::Confirmed => 'success',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * @return list<self>
     */
    public static function blocking(): array
    {
        return [self::Option, self::Confirmed];
    }
}
