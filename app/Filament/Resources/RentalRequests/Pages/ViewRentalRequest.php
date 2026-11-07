<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRentalRequest extends ViewRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [...RentalRequestResource::statusActions(), EditAction::make()];
    }
}
