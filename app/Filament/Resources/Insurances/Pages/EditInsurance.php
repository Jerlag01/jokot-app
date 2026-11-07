<?php

namespace App\Filament\Resources\Insurances\Pages;

use App\Filament\Resources\Insurances\InsuranceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditInsurance extends EditRecord
{
    protected static string $resource = InsuranceResource::class;

    /**
     * @var list<string>
     */
    private array $previousDocuments = [];

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function beforeSave(): void
    {
        $this->previousDocuments = array_values($this->getRecord()->getOriginal('documents') ?? []);
    }

    /**
     * Documents removed in the form are removed from the disk too.
     */
    protected function afterSave(): void
    {
        Storage::disk('local')->delete(array_diff($this->previousDocuments, array_values($this->getRecord()->documents ?? [])));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
