<?php

namespace App\Filament\Resources\Invoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Alle'),
            'draft' => Tab::make('Concepten')
                ->badge(Invoice::query()->where('status', InvoiceStatus::Draft)->count() ?: null)->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Draft)),
            'open' => Tab::make('Openstaand')
                ->modifyQueryUsing(fn (Builder $query) => $query->outstanding()),
            'overdue' => Tab::make('Vervallen')
                ->badge(Invoice::query()->overdue()->count() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'paid' => Tab::make('Betaald')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Paid)),
        ];
    }
}
