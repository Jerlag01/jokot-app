<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Support\Permissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingTicketSales extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Ticketverkoop komende evenementen';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasAnyPermission(Permissions::EVENTS, Permissions::TICKET_ORDERS)
            && Event::query()->where('tickets_enabled', true)->where('starts_at', '>=', today())->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Event::query()->where('tickets_enabled', true)->where('starts_at', '>=', today())->orderBy('starts_at')
                ->withCount(['tickets' => fn (Builder $query) => $query->whereNull('cancelled_at')])
                ->withSum(['orders' => fn (Builder $query) => $query->where('status', OrderStatus::Paid)], 'total'))
            ->paginated([5])
            ->recordUrl(fn (Event $record): string => EventResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('starts_at')->label('Datum')->dateTime('D d/m H:i'),
                TextColumn::make('title')->label('Evenement'),
                TextColumn::make('tickets_count')->label('Tickets')->formatStateUsing(fn (int $state, Event $record): string => $state.($record->capacity ? ' / '.$record->capacity : '')),
                TextColumn::make('orders_sum_total')->label('Omzet')->money('EUR')->placeholder('€0'),
            ]);
    }
}
