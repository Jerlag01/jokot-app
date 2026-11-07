<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * How an invoice payment came in.
 */
enum PaymentMethod: string implements HasColor, HasLabel
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Card = 'card';
    case Stripe = 'stripe';
    case SumUp = 'sumup';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bank => 'Overschrijving',
            self::Cash => 'Cash',
            self::Card => 'Bancontact/kaart',
            self::Stripe => 'Stripe',
            self::SumUp => 'SumUp',
            self::Other => 'Andere',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bank => 'info',
            self::Cash => 'success',
            self::Card => 'primary',
            self::Stripe => 'primary',
            self::SumUp => 'primary',
            self::Other => 'gray',
        };
    }
}
