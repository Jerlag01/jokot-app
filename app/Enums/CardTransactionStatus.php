<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Only completed transactions count for the balance. Online top-ups are pending until the payment comes in.
 */
enum CardTransactionStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Wacht op betaling',
            self::Completed => 'Voltooid',
            self::Failed => 'Niet betaald',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Completed => 'success',
            self::Failed => 'gray',
        };
    }
}
