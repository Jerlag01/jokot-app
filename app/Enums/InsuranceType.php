<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InsuranceType: string implements HasLabel
{
    case Liability = 'liability';
    case Fire = 'fire';
    case Accidents = 'accidents';
    case LegalAid = 'legal_aid';
    case Directors = 'directors';
    case Events = 'events';
    case Equipment = 'equipment';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Liability => 'Burgerlijke aansprakelijkheid (BA)',
            self::Fire => 'Brand',
            self::Accidents => 'Lichamelijke ongevallen (LO)',
            self::LegalAid => 'Rechtsbijstand',
            self::Directors => 'Bestuurdersaansprakelijkheid',
            self::Events => 'Evenementen',
            self::Equipment => 'Materiaal / alle risico\'s',
            self::Other => 'Andere',
        };
    }
}
