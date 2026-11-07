<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Why a stock quantity changed. Quantities are signed: purchases add, everything else usually subtracts.
 */
enum StockMovementType: string implements HasColor, HasLabel
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Rental = 'rental';
    case Count = 'count';
    case Loss = 'loss';
    case Correction = 'correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::Purchase => 'Aankoop',
            self::Sale => 'Verkoop (toog)',
            self::Rental => 'Verhuur',
            self::Count => 'Telling (verschil)',
            self::Loss => 'Breuk/verlies',
            self::Correction => 'Correctie',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Purchase => 'success',
            self::Sale => 'info',
            self::Rental => 'warning',
            self::Count => 'gray',
            self::Loss => 'danger',
            self::Correction => 'gray',
        };
    }
}
