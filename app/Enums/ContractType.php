<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Which template a contract uses.
 */
enum ContractType: string implements HasColor, HasLabel
{
    case Rental = 'rental';
    case CupLoan = 'cup_loan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rental => 'Gebruiksovereenkomst zaal',
            self::CupLoan => 'Overeenkomst wisselbekers',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Rental => 'primary',
            self::CupLoan => 'info',
        };
    }
}
