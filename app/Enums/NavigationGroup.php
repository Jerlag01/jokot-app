<?php

namespace App\Enums;

use Filament\Navigation\NavigationGroup as FilamentNavigationGroup;
use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Rentals = 'rentals';
    case CupLoans = 'cup_loans';
    case Tickets = 'tickets';
    case Finance = 'finance';
    case Stock = 'stock';
    case Members = 'members';
    case Administration = 'administration';
    case Website = 'website';
    case Security = 'security';
    case System = 'system';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rentals => 'Verhuur',
            self::CupLoans => 'Wisselbekers',
            self::Tickets => 'Evenementen & tickets',
            self::Finance => 'Facturatie',
            self::Stock => 'Stock',
            self::Members => 'Leden',
            self::Administration => 'Administratie',
            self::Website => 'Website',
            self::Security => 'Beveiliging',
            self::System => 'Systeem',
        };
    }

    /**
     * The groups in this order, for the panel's sidebar.
     *
     * @return list<FilamentNavigationGroup>
     */
    public static function navigationGroups(): array
    {
        return array_map(
            fn (self $case): FilamentNavigationGroup => FilamentNavigationGroup::make(fn (): string => $case->getLabel()),
            self::cases(),
        );
    }
}
