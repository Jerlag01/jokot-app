<?php

namespace App\Filament\Resources\RentalExtras\Pages;

use App\Filament\Resources\RentalExtras\RentalExtraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalExtras extends ManageRecords
{
    protected static string $resource = RentalExtraResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
