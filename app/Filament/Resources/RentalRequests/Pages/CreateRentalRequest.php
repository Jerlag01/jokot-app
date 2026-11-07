<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRentalRequest extends CreateRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
