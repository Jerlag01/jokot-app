<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * What a product is, for grouping in stock counts and price lists.
 */
enum ProductCategory: string implements HasColor, HasLabel
{
    case Soft = 'soft';
    case Beer = 'beer';
    case Wine = 'wine';
    case Snack = 'snack';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Soft => 'Frisdrank & water',
            self::Beer => 'Bier',
            self::Wine => 'Wijn & cava',
            self::Snack => 'Snacks',
            self::Other => 'Andere',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Soft => 'info',
            self::Beer => 'warning',
            self::Wine => 'danger',
            self::Snack => 'success',
            self::Other => 'gray',
        };
    }
}
