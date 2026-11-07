<?php

namespace App\Filament\Resources\CupLoans\Pages;

use App\Filament\Resources\CupLoans\CupLoanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCupLoan extends ViewRecord
{
    protected static string $resource = CupLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [...CupLoanResource::lifecycleActions(), EditAction::make()];
    }
}
