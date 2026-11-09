<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Enums\RentalStatus;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\RentalRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRentalRequests extends ListRecords
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('Alle')];

        foreach ([RentalStatus::New, RentalStatus::Option, RentalStatus::Confirmed] as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->badge(RentalRequest::query()->where('status', $status)->whereDate('event_date', '>=', today())->count() ?: null)
                ->badgeColor($status->getColor())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status));
        }

        $tabs['closed'] = Tab::make('Geweigerd/geannuleerd')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [RentalStatus::Rejected, RentalStatus::Cancelled, RentalStatus::Withdrawn]));

        return $tabs;
    }
}
