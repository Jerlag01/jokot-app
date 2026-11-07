<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRentalRequest extends EditRecord
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
