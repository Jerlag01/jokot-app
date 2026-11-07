<?php

namespace App\Filament\Resources\RentalRates\Pages;

use App\Filament\Resources\RentalRates\RentalRateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalRates extends ManageRecords
{
    protected static string $resource = RentalRateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
