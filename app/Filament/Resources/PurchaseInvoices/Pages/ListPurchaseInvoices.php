<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Support\Billit\Billit;
use App\Support\Billit\BillitClient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('billitImport')
                ->label('Ophalen uit Billit')
                ->icon(Heroicon::OutlinedCloudArrowDown)
                ->color('gray')
                ->visible(fn (): bool => app(BillitClient::class)->isConfigured())
                ->requiresConfirmation()
                ->modalDescription('Inkomende facturen en creditnota\'s die in Billit binnenkwamen (bv. via Peppol) worden met hun PDF ingeboekt. Wat al ingeboekt is, wordt overgeslagen.')
                ->action(function (): void {
                    try {
                        $count = app(Billit::class)->importIncoming();
                        Notification::make()->success()->title($count.' facturen opgehaald uit Billit')->send();
                    } catch (\Throwable $exception) {
                        Notification::make()->danger()->title('Ophalen mislukt')->body($exception->getMessage())->send();
                    }
                }),
            CreateAction::make()->label('Factuur inboeken'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'open' => Tab::make('Te betalen')
                ->badge(PurchaseInvoice::query()->outstanding()->count() ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->outstanding()),
            'overdue' => Tab::make('Vervallen')
                ->badge(PurchaseInvoice::query()->overdue()->count() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue()),
            'paid' => Tab::make('Betaald')->modifyQueryUsing(fn (Builder $query) => $query->where('status', InvoiceStatus::Paid)),
            'all' => Tab::make('Alle'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'open';
    }
}
