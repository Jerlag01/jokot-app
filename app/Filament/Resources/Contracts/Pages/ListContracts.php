<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Filament\Resources\Contracts\ContractResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    /**
     * One tab per category the user may see, plus "waiting for signature".
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Alle')];

        foreach (ContractResource::allowedTypes() as $type) {
            $tabs[$type->value] = Tab::make($type === ContractType::Rental ? 'Zaalverhuur' : 'Wisselbekers')
                ->badge(ContractResource::getEloquentQuery()->where('type', $type)->count() ?: null)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type));
        }

        $tabs['waiting'] = Tab::make('Wacht op handtekening')
            ->badgeColor('warning')
            ->badge(ContractResource::getEloquentQuery()->where('status', ContractStatus::Sent)->count() ?: null)
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ContractStatus::Sent));

        return $tabs;
    }
}
