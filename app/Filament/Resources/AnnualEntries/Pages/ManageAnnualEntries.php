<?php

namespace App\Filament\Resources\AnnualEntries\Pages;

use App\Filament\Resources\AnnualEntries\AnnualEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAnnualEntries extends ManageRecords
{
    protected static string $resource = AnnualEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->mutateDataUsing(fn (array $data): array => [...$data, 'user_id' => auth()->id()])];
    }
}
