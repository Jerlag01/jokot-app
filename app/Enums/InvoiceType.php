<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * An invoice or a credit note; a credit note's amounts count negatively in the totals.
 */
enum InvoiceType: string implements HasColor, HasLabel
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function getLabel(): string
    {
        return match ($this) {
            self::Invoice => 'Factuur',
            self::CreditNote => 'Creditnota',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Invoice => 'primary',
            self::CreditNote => 'warning',
        };
    }
}
