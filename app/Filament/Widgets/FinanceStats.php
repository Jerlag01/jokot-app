<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Support\Permissions;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Facturatie';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::INVOICES);
    }

    protected function getStats(): array
    {
        $open = Invoice::query()->outstanding()->get(['total', 'paid_total']);
        $overdue = Invoice::query()->overdue()->get(['total', 'paid_total']);

        return [
            Stat::make('Openstaand', euro($open->sum(fn (Invoice $invoice): float => $invoice->balance())))
                ->description($open->count().' facturen')
                ->descriptionIcon(Heroicon::OutlinedClock)
                ->color('info')
                ->url(InvoiceResource::getUrl('index', ['tab' => 'open'])),
            Stat::make('Vervallen', euro($overdue->sum(fn (Invoice $invoice): float => $invoice->balance())))
                ->description($overdue->count().' facturen')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdue->isEmpty() ? 'success' : 'danger')
                ->url(InvoiceResource::getUrl('index', ['tab' => 'overdue'])),
            Stat::make('Ontvangen deze maand', euro((float) InvoicePayment::query()->whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount')))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->description('Betalingen op facturen')
                ->color('success'),
            Stat::make('Concepten', Invoice::query()->where('status', InvoiceStatus::Draft)->where('type', InvoiceType::Invoice)->count())
                ->description('Nog niet definitief')
                ->descriptionIcon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->url(InvoiceResource::getUrl('index', ['tab' => 'draft'])),
        ];
    }
}
