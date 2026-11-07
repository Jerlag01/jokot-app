<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Who handled a ticket payment.
 */
enum PaymentProvider: string implements HasColor, HasLabel
{
    case Stripe = 'stripe';
    case SumUp = 'sumup';
    case Cash = 'cash';
    case Card = 'card';
    case Free = 'free';

    public function getLabel(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::SumUp => 'SumUp',
            self::Cash => 'Kassa (cash)',
            self::Card => 'Kassa (kaart)',
            self::Free => 'Gratis',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Stripe => 'primary',
            self::SumUp => 'info',
            self::Cash => 'success',
            self::Card => 'success',
            self::Free => 'gray',
        };
    }

    public function isOnline(): bool
    {
        return in_array($this, [self::Stripe, self::SumUp], true);
    }
}
