<?php

namespace App\Filament\Resources\OpeningHours\Pages;

use App\Filament\Resources\OpeningHours\OpeningHourResource;
use Filament\Resources\Pages\ManageRecords;

class ManageOpeningHours extends ManageRecords
{
    protected static string $resource = OpeningHourResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
