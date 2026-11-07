<?php

namespace App\Filament\Resources\CupLoans\Pages;

use App\Filament\Resources\CupLoans\CupLoanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCupLoan extends CreateRecord
{
    protected static string $resource = CupLoanResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
