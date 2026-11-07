<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * A draft has no number yet; finalising gives it the next number of the year.
 */
enum InvoiceStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Open = 'open';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Concept',
            self::Open => 'Openstaand',
            self::PartiallyPaid => 'Deels betaald',
            self::Paid => 'Betaald',
            self::Cancelled => 'Geannuleerd',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }
}
