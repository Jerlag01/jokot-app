<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A contract is frozen (body and PDF) once signed.
 */
enum ContractStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Signed = 'signed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Concept',
            self::Sent => 'Verstuurd',
            self::Signed => 'Ondertekend',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'info',
            self::Signed => 'success',
        };
    }
}
