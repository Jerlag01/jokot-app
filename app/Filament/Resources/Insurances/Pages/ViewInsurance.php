<?php

namespace App\Filament\Resources\Insurances\Pages;

use App\Filament\Resources\Insurances\InsuranceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInsurance extends ViewRecord
{
    protected static string $resource = InsuranceResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
