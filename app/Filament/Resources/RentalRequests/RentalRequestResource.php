<?php

namespace App\Filament\Resources\RentalRequests;

use App\Enums\NavigationGroup;
use App\Enums\RentalStatus;
use App\Filament\Actions\ContractActions;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\RentalRequests\Pages\CreateRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\EditRentalRequest;
use App\Filament\Resources\RentalRequests\Pages\ListRentalRequests;
use App\Filament\Resources\RentalRequests\Pages\ViewRentalRequest;
use App\Models\Product;
use App\Models\RentalDrinkCount;
use App\Models\RentalExtra;
use App\Models\RentalRate;
use App\Models\RentalRequest;
use App\Support\Availability;
use App\Support\Contracts;
use App\Support\Invoicing;
use App\Support\Permissions;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnitEnum;

class RentalRequestResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::RENTALS;

    protected static ?string $model = RentalRequest::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'huuraanvraag';

    protected static ?string $pluralModelLabel = 'huuraanvragen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $new = RentalRequest::query()->where('status', RentalStatus::New)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'first_name', 'last_name', 'organisation', 'email'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aanvraag')
                ->columns(3)
                ->schema([
                    DatePicker::make('event_date')
                        ->label('Datum')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->rules([
                            fn (?RentalRequest $record, Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record, $get): void {
                                $status = $get('status');
                                $status = $status instanceof RentalStatus ? $status : RentalStatus::tryFrom((string) $status);

                                if (in_array($status, RentalStatus::blocking(), true)
                                    && ! app(Availability::class)->isAvailable(CarbonImmutable::parse($value), $record?->id)) {
                                    $fail('Op deze datum is er al een verhuur in optie/bevestigd of een eigen activiteit.');
                                }
                            },
                        ]),
                    Select::make('status')
                        ->label('Status')
                        ->options(RentalStatus::class)
                        ->default(RentalStatus::New)
                        ->required(),
                    TextInput::make('expected_guests')->label('Verwacht aantal')->numeric()->minValue(1)->maxValue(350),
                    Select::make('rental_rate_id')
                        ->label('Gebruikersgroep')
                        ->relationship('rentalRate', 'name')
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillQuote($get, $set)),
                    Select::make('rental_extra_id')
                        ->label('DJ-tafel')
                        ->relationship('rentalExtra', 'name')
                        ->placeholder('Nee')
                        ->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::fillQuote($get, $set)),
                    Toggle::make('brings_own_wine')->label('Eigen wijn/cava/champagne (kurkgeld)')->inline(false),
                    TextInput::make('quoted_price')->label('Huurprijs')->numeric()->prefix('€'),
                    TextInput::make('quoted_deposit')->label('Waarborg')->numeric()->prefix('€'),
                    Textarea::make('details')->label('Details van de aanvrager')->rows(3)->columnSpanFull(),
                ]),
            Section::make('Aanvrager')
                ->columns(3)
                ->schema([
                    TextInput::make('first_name')->label('Voornaam')->required(),
                    TextInput::make('last_name')->label('Achternaam')->required(),
                    TextInput::make('organisation')->label('Organisatie'),
                    TextInput::make('company_number')->label('Ondernemingsnummer'),
                    TextInput::make('email')->label('E-mail')->email()->required(),
                    TextInput::make('phone')->label('Telefoon')->tel()->required(),
                    Grid::make(5)->columnSpanFull()->schema([
                        TextInput::make('street')->label('Straat')->required()->columnSpan(2),
                        TextInput::make('house_number')->label('Nr.')->required(),
                        TextInput::make('box')->label('Bus'),
                        TextInput::make('postal_code')->label('Postcode')->required(),
                    ]),
                    TextInput::make('city')->label('Gemeente')->required(),
                ]),
            Section::make('Drank')
                ->collapsible()
                ->schema([
                    Repeater::make('drinks')
                        ->hiddenLabel()
                        ->relationship()
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Drank toevoegen')
                        ->schema([
                            Select::make('product_id')
                                ->label('Drank')
                                ->options(fn (): array => Product::query()->active()->get()->mapWithKeys(fn (Product $drink): array => [$drink->id => $drink->label()])->all())
                                ->live()
                                ->afterStateUpdated(fn (?string $state, Set $set) => $set('name', Product::query()->find($state)?->label()))
                                ->required(),
                            TextInput::make('quantity')->label('Aantal')->numeric()->minValue(1)->required(),
                            TextInput::make('name')->hidden()->dehydratedWhenHidden(),
                        ]),
                ]),
            Section::make('Intern')
                ->schema([
                    Textarea::make('admin_notes')->label('Interne notities')->rows(3)->helperText('Enkel zichtbaar voor het bestuur.'),
                ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aanvraag')
                ->columns(4)
                ->schema([
                    TextEntry::make('reference')->label('Referentie')->copyable(),
                    TextEntry::make('status')->label('Status')->badge(),
                    TextEntry::make('event_date')->label('Datum')->date('l j F Y'),
                    TextEntry::make('expected_guests')->label('Verwacht aantal')->placeholder('-'),
                    TextEntry::make('rentalRate.name')->label('Gebruikersgroep')->placeholder('-'),
                    TextEntry::make('rentalExtra.name')->label('DJ-tafel')->placeholder('Nee'),
                    IconEntry::make('brings_own_wine')->label('Eigen wijn/cava')->boolean(),
                    TextEntry::make('quote')
                        ->label('Huurprijs / waarborg')
                        ->state(fn (RentalRequest $record): string => $record->quoted_price === null ? '-' : euro($record->quoted_price).' / '.euro($record->quoted_deposit)),
                    TextEntry::make('details')->label('Details')->placeholder('-')->columnSpanFull(),
                ]),
            Section::make('Aanvrager')
                ->columns(3)
                ->schema([
                    TextEntry::make('name')->label('Naam')->state(fn (RentalRequest $record): string => $record->fullName()),
                    TextEntry::make('organisation')->label('Organisatie')->placeholder('-'),
                    TextEntry::make('company_number')->label('Ondernemingsnummer')->placeholder('-'),
                    TextEntry::make('email')->label('E-mail')->url(fn (RentalRequest $record): string => 'mailto:'.$record->email)->copyable(),
                    TextEntry::make('phone')->label('Telefoon')->url(fn (RentalRequest $record): string => 'tel:'.preg_replace('/[^0-9+]/', '', $record->phone)),
                    TextEntry::make('address')->label('Adres')->state(fn (RentalRequest $record): string => $record->address()),
                ]),
            Section::make('Drank')
                ->schema([
                    RepeatableEntry::make('drinks')
                        ->hiddenLabel()
                        ->columns(2)
                        ->placeholder('Geen drank opgegeven.')
                        ->schema([
                            TextEntry::make('name')->hiddenLabel(),
                            TextEntry::make('quantity')->hiddenLabel()->suffix(' ×'),
                        ]),
                ]),
            Section::make('Geannuleerd door aanvrager')
                ->visible(fn (RentalRequest $record): bool => $record->status === RentalStatus::Withdrawn)
                ->columns(2)
                ->schema([
                    TextEntry::make('cancellation_reason')->label('Reden')->columnSpanFull(),
                    TextEntry::make('cancelled_at')->label('Teruggetrokken op')->dateTime('d/m/Y H:i'),
                    TextEntry::make('cancelledBy.name')->label('Door')->placeholder('-'),
                ]),
            Section::make('Opvolging')
                ->columns(4)
                ->schema([
                    TextEntry::make('contract.status')->label('Contract')->badge()->placeholder('Nog niet verstuurd'),
                    TextEntry::make('deposit_paid_at')->label('Waarborg ontvangen')->dateTime('d/m/Y')->placeholder('Nog niet'),
                    TextEntry::make('invoice')->label('Factuur')->placeholder('-')
                        ->state(fn (RentalRequest $record): ?string => $record->invoice ? $record->invoice->displayNumber().' ('.$record->invoice->status->getLabel().')' : null)
                        ->url(fn (RentalRequest $record): ?string => $record->invoice ? InvoiceResource::getUrl('view', ['record' => $record->invoice]) : null),
                    TextEntry::make('corkage_bottles')->label('Kurkgeld (flessen)'),
                    TextEntry::make('drink_count')->label('Drankentelling (voor → na = verbruikt)')->columnSpanFull()
                        ->placeholder('Nog niet geteld')
                        ->state(fn (RentalRequest $record): ?string => $record->drinkCounts->isEmpty() ? null : $record->drinkCounts->load('product')
                            ->map(fn (RentalDrinkCount $count): string => "{$count->product->name}: {$count->quantity_before} → {$count->quantity_after} = {$count->consumed()}")
                            ->implode(' · ')),
                ]),
            Section::make('Intern')
                ->columns(3)
                ->schema([
                    TextEntry::make('admin_notes')->label('Interne notities')->placeholder('-')->columnSpanFull(),
                    TextEntry::make('created_at')->label('Ontvangen')->dateTime('d/m/Y H:i'),
                    TextEntry::make('terms_accepted_at')->label('Voorwaarden aanvaard')->dateTime('d/m/Y H:i')->placeholder('-'),
                    TextEntry::make('ip_address')->label('IP-adres')->placeholder('-'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('event_date')
            ->columns([
                TextColumn::make('reference')->label('Ref.')->searchable()->sortable(),
                TextColumn::make('event_date')->label('Datum')->date('D d/m/Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('name')
                    ->label('Aanvrager')
                    ->state(fn (RentalRequest $record): string => $record->fullName())
                    ->description(fn (RentalRequest $record): ?string => $record->organisation)
                    ->searchable(['first_name', 'last_name', 'organisation']),
                TextColumn::make('rentalRate.name')->label('Groep')->toggleable(),
                TextColumn::make('expected_guests')->label('Volk')->numeric()->toggleable(),
                TextColumn::make('quoted_price')->label('Huur')->money('EUR')->toggleable(),
                TextColumn::make('email')->label('E-mail')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')->label('Telefoon')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Ontvangen')->since()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(RentalStatus::class)->multiple(),
                Filter::make('upcoming')
                    ->label('Enkel komende')
                    ->default()
                    ->query(fn (Builder $query) => $query->whereDate('event_date', '>=', today())),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }

    /**
     * Header actions to move a request through its statuses without opening the edit form.
     *
     * @return list<Action>
     */
    public static function statusActions(): array
    {
        $make = fn (string $name, RentalStatus $status, string $label, Heroicon $icon, array $from): Action => Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($status->getColor())
            ->visible(fn (RentalRequest $record): bool => in_array($record->status, $from, true))
            ->requiresConfirmation()
            ->action(function (RentalRequest $record) use ($status): void {
                if (in_array($status, RentalStatus::blocking(), true) && ! app(Availability::class)->isAvailable($record->event_date, $record->id)) {
                    Notification::make()->danger()->title('Datum niet meer vrij')->body('Er is op deze datum al een verhuur in optie/bevestigd of een eigen activiteit.')->send();

                    return;
                }

                $record->update(['status' => $status]);
                Notification::make()->success()->title('Status: '.$status->getLabel())->send();
            });

        return [
            ...self::followUpActions(),
            $make('option', RentalStatus::Option, 'In optie zetten', Heroicon::OutlinedClock, [RentalStatus::New]),
            $make('confirm', RentalStatus::Confirmed, 'Bevestigen', Heroicon::OutlinedCheckCircle, [RentalStatus::New, RentalStatus::Option]),
            $make('reject', RentalStatus::Rejected, 'Weigeren', Heroicon::OutlinedXCircle, [RentalStatus::New, RentalStatus::Option]),
            $make('cancel', RentalStatus::Cancelled, 'Annuleren', Heroicon::OutlinedNoSymbol, [RentalStatus::Confirmed]),
            self::withdrawAction(),
        ];
    }

    /**
     * The requester calls the rental off: the contract is removed, the request is kept with the reason,
     * and the date is free again on the request form.
     */
    public static function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label('Terugtrekken (aanvrager annuleert)')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (RentalRequest $record): bool => in_array($record->status, RentalStatus::withdrawable(), true))
            ->modalHeading('Aanvraag terugtrekken')
            ->modalDescription(fn (RentalRequest $record): string => 'De aanvrager annuleert de huur van '.$record->event_date->format('d/m/Y').'. '
                .($record->contract ? 'Het contract'.($record->contract->isSigned() ? ' (ondertekend)' : '').' wordt verwijderd. ' : '')
                .'De aanvraag blijft bewaard als "Geannuleerd door aanvrager" en de datum is weer vrij op het aanvraagformulier.')
            ->schema([
                Textarea::make('reason')->label('Reden van annulering')->required()->rows(3)->maxLength(2000),
            ])
            ->modalSubmitActionLabel('Terugtrekken')
            ->action(function (RentalRequest $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    if ($contract = $record->contract()->first()) {
                        app(Contracts::class)->delete($contract);
                    }

                    $record->update([
                        'status' => RentalStatus::Withdrawn,
                        'cancellation_reason' => $data['reason'],
                        'cancelled_at' => now(),
                        'cancelled_by' => auth()->id(),
                    ]);
                });

                $record->unsetRelation('contract');

                Notification::make()->success()->title('Aanvraag teruggetrokken')
                    ->body($record->invoice ? 'Let op: er is al een factuur. Maak indien nodig een creditnota.' : 'De datum is weer vrij.')
                    ->send();
            });
    }

    /**
     * Contract, deposit, drink count and invoice: the steps after confirming a rental.
     *
     * @return list<Action|ActionGroup>
     */
    public static function followUpActions(): array
    {
        $active = fn (RentalRequest $record): bool => in_array($record->status, [RentalStatus::Option, RentalStatus::Confirmed], true);

        return [
            ContractActions::group($active),
            Action::make('depositPaid')
                ->label(fn (RentalRequest $record): string => $record->deposit_paid_at ? 'Waarborg niet ontvangen' : 'Waarborg ontvangen')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color(fn (RentalRequest $record): string => $record->deposit_paid_at ? 'gray' : 'success')
                ->visible($active)
                ->requiresConfirmation()
                ->action(fn (RentalRequest $record) => $record->update(['deposit_paid_at' => $record->deposit_paid_at ? null : now()])),
            Action::make('drinkCount')
                ->label('Drankentelling')
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('gray')
                ->visible(fn (RentalRequest $record): bool => $record->status === RentalStatus::Confirmed && ! $record->drinks_booked_at)
                ->modalHeading('Drankentelling voor en na het evenement')
                ->modalDescription('Tel per product het aantal stuks (vaten: aantal vaten) voor en na. Het verschil komt op de factuur aan de prijs voor huurders. Gesloten vaten tellen niet als verbruikt.')
                ->modalWidth('3xl')
                ->fillForm(fn (RentalRequest $record): array => [
                    'corkage_bottles' => $record->corkage_bottles,
                    'counts' => $record->drinkCounts->mapWithKeys(fn (RentalDrinkCount $count): array => [$count->product_id => ['before' => $count->quantity_before, 'after' => $count->quantity_after]])->all(),
                ])
                ->schema(fn (): array => [
                    ...Product::query()->active()->whereNotNull('rental_price')->get()->map(fn (Product $product) => Grid::make(3)->schema([
                        Text::make($product->name.' ('.euro($product->rental_price).')'),
                        TextInput::make("counts.{$product->id}.before")->hiddenLabel()->placeholder('Voor')->numeric()->minValue(0),
                        TextInput::make("counts.{$product->id}.after")->hiddenLabel()->placeholder('Na')->numeric()->minValue(0),
                    ]))->all(),
                    TextInput::make('corkage_bottles')->label('Geopende flessen eigen wijn/cava (kurkgeld)')->numeric()->minValue(0)->default(0),
                ])
                ->action(function (RentalRequest $record, array $data): void {
                    foreach ($data['counts'] ?? [] as $productId => $count) {
                        if (blank($count['before'] ?? null) && blank($count['after'] ?? null)) {
                            $record->drinkCounts()->where('product_id', $productId)->delete();

                            continue;
                        }

                        $record->drinkCounts()->updateOrCreate(['product_id' => $productId], [
                            'quantity_before' => (int) ($count['before'] ?? 0),
                            'quantity_after' => (int) ($count['after'] ?? 0),
                        ]);
                    }

                    $record->update(['corkage_bottles' => (int) ($data['corkage_bottles'] ?? 0)]);
                    Notification::make()->success()->title('Telling opgeslagen')->send();
                }),
            Action::make('invoice')
                ->label(fn (RentalRequest $record): string => $record->invoice_id ? 'Factuur openen' : 'Factuur maken')
                ->icon(Heroicon::OutlinedDocumentCurrencyEuro)
                ->color('primary')
                ->visible(fn (RentalRequest $record): bool => $record->status === RentalStatus::Confirmed && auth()->user()->hasPermission(Permissions::INVOICES))
                ->requiresConfirmation(fn (RentalRequest $record): bool => ! $record->invoice_id)
                ->modalDescription('Er wordt een conceptfactuur gemaakt met de zaalhuur, DJ-installatie, het verbruik uit de drankentelling, kurkgeld en min de betaalde waarborg. Het verbruik wordt uit de stock geboekt.')
                ->action(function (RentalRequest $record) {
                    try {
                        $invoice = $record->invoice ?? app(Invoicing::class)->forRental($record);

                        return redirect(InvoiceResource::getUrl($invoice->isDraft() ? 'edit' : 'view', ['record' => $invoice]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }

    private static function fillQuote(Get $get, Set $set): void
    {
        $rate = RentalRate::query()->find($get('rental_rate_id'));
        $extra = RentalExtra::query()->find($get('rental_extra_id'));

        if ($rate) {
            $set('quoted_price', (float) $rate->price + (float) $extra?->price);
            $set('quoted_deposit', (float) $rate->deposit + (float) $extra?->deposit);
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRentalRequests::route('/'),
            'create' => CreateRentalRequest::route('/create'),
            'view' => ViewRentalRequest::route('/{record}'),
            'edit' => EditRentalRequest::route('/{record}/edit'),
        ];
    }
}
