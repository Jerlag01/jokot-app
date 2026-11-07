<?php

namespace App\Filament\Resources\CupLoans\Pages;

use App\Filament\Resources\CupLoans\CupLoanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCupLoan extends EditRecord
{
    protected static string $resource = CupLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
