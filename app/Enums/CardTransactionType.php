<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A movement on the budget of a member card. Top-ups are positive, purchases negative.
 */
enum CardTransactionType: string implements HasColor, HasLabel
{
    case TopUpOnline = 'topup_online';
    case TopUpBar = 'topup_bar';
    case Purchase = 'purchase';
    case Refund = 'refund';
    case Correction = 'correction';

    public function getLabel(): string
    {
        return match ($this) {
            self::TopUpOnline => 'Online opgeladen',
            self::TopUpBar => 'Opgeladen aan de toog',
            self::Purchase => 'Aankoop',
            self::Refund => 'Terugbetaald',
            self::Correction => 'Correctie',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::TopUpOnline, self::TopUpBar => 'success',
            self::Purchase => 'gray',
            self::Refund => 'warning',
            self::Correction => 'info',
        };
    }
}
