<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Support\Permissions;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PurchaseStats extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Inkomende facturen';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::PURCHASES);
    }

    private function costs(string $from, string $until): float
    {
        return (float) PurchaseInvoice::query()
            ->where('status', '!=', InvoiceStatus::Cancelled)
            ->whereBetween('issue_date', [$from, $until])
            ->sum(DB::raw("CASE WHEN type = '".InvoiceType::CreditNote->value."' THEN -total ELSE total END"));
    }

    protected function getStats(): array
    {
        $open = PurchaseInvoice::query()->outstanding()->get(['total', 'paid_total']);
        $overdue = PurchaseInvoice::query()->overdue()->get(['total', 'paid_total']);
        $dueSoon = PurchaseInvoice::query()->outstanding()->whereBetween('due_date', [today(), today()->addDays(7)])->count();

        return [
            Stat::make('Te betalen', euro($open->sum(fn (PurchaseInvoice $invoice): float => $invoice->balance())))
                ->description($open->count().' facturen'.($dueSoon ? ", {$dueSoon} vervalt deze week" : ''))
                ->descriptionIcon(Heroicon::OutlinedInboxArrowDown)
                ->color($dueSoon ? 'warning' : 'info')
                ->url(PurchaseInvoiceResource::getUrl('index', ['tab' => 'open'])),
            Stat::make('Vervallen', euro($overdue->sum(fn (PurchaseInvoice $invoice): float => $invoice->balance())))
                ->description($overdue->count().' facturen')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($overdue->isEmpty() ? 'success' : 'danger')
                ->url(PurchaseInvoiceResource::getUrl('index', ['tab' => 'overdue'])),
            Stat::make('Kosten deze maand', euro($this->costs(now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString())))
                ->description('Volgens factuurdatum')
                ->descriptionIcon(Heroicon::OutlinedCalendar)
                ->color('gray'),
            Stat::make('Kosten '.now()->year, euro($this->costs(now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString())))
                ->descriptionIcon(Heroicon::OutlinedChartBar)
                ->description('Sinds 1 januari')
                ->color('gray'),
        ];
    }
}
