<?php

namespace App\Filament\Resources\CupLoans\Pages;

use App\Filament\Resources\CupLoans\CupLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCupLoans extends ListRecords
{
    protected static string $resource = CupLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
