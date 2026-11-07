<?php

namespace App\Filament\Resources\Orders;

use App\Enums\NavigationGroup;
use App\Enums\OrderStatus;
use App\Enums\PaymentProvider;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\Order;
use App\Models\Ticket;
use App\Support\Permissions;
use App\Support\Ticketing\TicketSales;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

class OrderResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::TICKET_ORDERS;

    protected static ?string $model = Order::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'bestelling';

    protected static ?string $pluralModelLabel = 'bestellingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tickets;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'number';

    /**
     * Admin URLs use the id; the public order page uses the token.
     */
    public static function getRecordRouteKeyName(): ?string
    {
        return 'id';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'name', 'email'];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(3)->schema([
                TextEntry::make('event.title')->label('Evenement'),
                TextEntry::make('name')->label('Naam'),
                TextEntry::make('email')->label('E-mail')->copyable()->placeholder('-'),
                TextEntry::make('phone')->label('Telefoon')->placeholder('-'),
                TextEntry::make('created_at')->label('Besteld')->dateTime('d/m/Y H:i'),
                TextEntry::make('paid_at')->label('Betaald')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('provider_reference')->label('Referentie provider')->placeholder('-')->copyable(),
                TextEntry::make('provider_transaction')->label('Transactie')->placeholder('-')->copyable(),
                TextEntry::make('user.name')->label('Verkocht door')->placeholder('Online'),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('provider')->label('Betaling')->badge()->placeholder('-'),
                TextEntry::make('total')->label('Totaal')->money('EUR')->size('lg')->weight('bold'),
            ]),
            Section::make('Tickets')->columnSpanFull()->schema([
                RepeatableEntry::make('tickets')->hiddenLabel()->placeholder('Nog geen tickets uitgereikt.')->table([
                    TableColumn::make('Code'), TableColumn::make('Type'), TableColumn::make('Naam'), TableColumn::make('Status'),
                ])->schema([
                    TextEntry::make('code')->fontFamily('mono')->copyable(),
                    TextEntry::make('ticketType.name'),
                    TextEntry::make('holder_name'),
                    TextEntry::make('state')->badge()
                        ->state(fn (Ticket $record): string => $record->cancelled_at ? 'Geannuleerd' : ($record->checked_in_at ? 'Binnen '.$record->checked_in_at->format('d/m H:i') : 'Geldig'))
                        ->color(fn (Ticket $record): string => $record->cancelled_at ? 'danger' : ($record->checked_in_at ? 'success' : 'gray')),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Nummer')->searchable(),
                TextColumn::make('event.title')->label('Evenement')->searchable()->toggleable(),
                TextColumn::make('name')->label('Naam')->description(fn (Order $record): ?string => $record->email ?: null)->searchable(['name', 'email']),
                TextColumn::make('tickets_count')->label('Tickets')->counts(['tickets' => fn ($query) => $query->whereNull('cancelled_at')]),
                TextColumn::make('total')->label('Totaal')->money('EUR')->summarize(Sum::make()->money('EUR')->label('')),
                TextColumn::make('provider')->label('Betaling')->badge()->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Besteld')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(OrderStatus::class)->multiple()->default([OrderStatus::Paid->value, OrderStatus::Pending->value]),
                SelectFilter::make('event')->label('Evenement')->relationship('event', 'title')->searchable()->preload(),
                SelectFilter::make('provider')->label('Betaling')->options(PaymentProvider::class),
            ])
            ->recordActions([ViewAction::make()]);
    }

    /**
     * @return list<Action>
     */
    public static function orderActions(): array
    {
        $fail = fn (Throwable $exception) => Notification::make()->danger()->title('Mislukt')->body($exception->getMessage())->persistent()->send();

        return [
            Action::make('sync')
                ->label('Betaling controleren')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->isPending() && (bool) $record->provider?->isOnline())
                ->action(function (Order $record) use ($fail): void {
                    try {
                        $record = app(TicketSales::class)->sync($record);
                        Notification::make()->title('Status: '.$record->status->getLabel())->send();
                    } catch (Throwable $exception) {
                        $fail($exception);
                    }
                }),
            Action::make('markPaid')
                ->label('Manueel als betaald')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Enkel gebruiken als je zeker bent dat er betaald is (bv. overschrijving). De tickets worden uitgereikt en gemaild.')
                ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Pending, OrderStatus::Expired], true))
                ->action(fn (Order $record) => app(TicketSales::class)->markPaid($record)),
            Action::make('resend')
                ->label('Tickets opnieuw mailen')
                ->icon(Heroicon::OutlinedEnvelope)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->isPaid() && filled($record->email))
                ->requiresConfirmation()
                ->action(function (Order $record): void {
                    app(TicketSales::class)->mailTickets($record);
                    Notification::make()->success()->title('Verstuurd naar '.$record->email)->send();
                }),
            Action::make('download')
                ->label('Tickets (PDF)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->visible(fn (Order $record): bool => $record->isPaid())
                ->action(function (Order $record) {
                    $pdf = app(TicketSales::class)->pdf($record->tickets()->valid()->get())->output();

                    return response()->streamDownload(fn () => print ($pdf), 'tickets-'.$record->number.'.pdf', ['Content-Type' => 'application/pdf']);
                }),
            Action::make('cancel')
                ->label(fn (Order $record): string => $record->isPaid() && $record->provider?->isOnline() ? 'Annuleren en terugbetalen' : 'Annuleren')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription(fn (Order $record): string => $record->isPaid() && $record->provider?->isOnline()
                    ? 'Het bedrag wordt via '.$record->provider->getLabel().' teruggestort en de tickets worden ongeldig.'
                    : 'De tickets worden ongeldig. Een cash- of kaartbetaling moet je zelf teruggeven.')
                ->visible(fn (Order $record): bool => in_array($record->status, [OrderStatus::Pending, OrderStatus::Paid], true))
                ->action(function (Order $record) use ($fail): void {
                    try {
                        app(TicketSales::class)->cancel($record, refund: true);
                        Notification::make()->success()->title('Bestelling '.$record->refresh()->status->getLabel())->send();
                    } catch (Throwable $exception) {
                        $fail($exception);
                    }
                }),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
