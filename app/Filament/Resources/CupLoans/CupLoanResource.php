<?php

namespace App\Filament\Resources\CupLoans;

use App\Enums\CupLoanStatus;
use App\Enums\NavigationGroup;
use App\Filament\Actions\ContractActions;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\CupLoans\Pages\CreateCupLoan;
use App\Filament\Resources\CupLoans\Pages\EditCupLoan;
use App\Filament\Resources\CupLoans\Pages\ListCupLoans;
use App\Filament\Resources\CupLoans\Pages\ViewCupLoan;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\CupLoan;
use App\Support\CupInventory;
use App\Support\Invoicing;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

/**
 * Lending reusable cups (wisselbekers): request, confirm (checks cups in stock), contract, pick-up, return, invoice.
 */
class CupLoanResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::CUP_LOANS;

    protected static ?string $model = CupLoan::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'uitlening';

    protected static ?string $pluralModelLabel = 'uitleningen';

    protected static ?string $navigationLabel = 'Uitleningen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::CupLoans;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $new = CupLoan::query()->where('status', CupLoanStatus::Requested)->count();

        return $new > 0 ? (string) $new : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Uitlening')->columns(4)->schema([
                Select::make('status')->label('Status')->options(CupLoanStatus::class)->default(CupLoanStatus::Requested)->required(),
                DatePicker::make('starts_on')->label('Ophalen')->required()->native(false)->displayFormat('d/m/Y'),
                DatePicker::make('ends_on')->label('Terugbrengen')->required()->native(false)->displayFormat('d/m/Y')->afterOrEqual('starts_on'),
                TextInput::make('event_name')->label('Evenement'),
                TextInput::make('cups')->label('Bekers')->numeric()->minValue(1)->required(),
                TextInput::make('crates')->label('Bakken')->numeric()->minValue(0)->default(0),
                TextInput::make('rental_fee')->label('Huurprijs')->numeric()->prefix('€')->default(fn () => settings()->get('cup_rental_fee')),
                Grid::make(2)->schema([
                    TextInput::make('cup_fee')->label('Per beker')->numeric()->prefix('€')->default(fn () => settings()->get('cup_fee')),
                    TextInput::make('crate_fee')->label('Per bak')->numeric()->prefix('€')->default(fn () => settings()->get('cup_crate_fee')),
                ]),
            ]),
            Section::make('Huurder')->columns(3)->schema([
                TextInput::make('organisation')->label('Organisatie')->required(),
                TextInput::make('representative')->label('Vertegenwoordigd door')->required(),
                TextInput::make('vat_number')->label('Ondernemingsnummer'),
                TextInput::make('email')->label('E-mail')->email()->required(),
                TextInput::make('phone')->label('Telefoon')->required(),
                Grid::make(5)->columnSpanFull()->schema([
                    TextInput::make('street')->label('Straat')->required()->columnSpan(2),
                    TextInput::make('house_number')->label('Nr.')->required(),
                    TextInput::make('box')->label('Bus'),
                    TextInput::make('postal_code')->label('Postcode')->required(),
                ]),
                TextInput::make('city')->label('Gemeente')->required(),
            ]),
            Section::make('Retour')->columns(4)->collapsed(fn (?CupLoan $record): bool => $record?->returned_at === null)->schema([
                TextInput::make('cups_returned')->label('Bekers terug')->numeric()->minValue(0),
                TextInput::make('cups_dirty')->label('Waarvan vuil/kapot')->numeric()->minValue(0)->default(0),
                TextInput::make('crates_returned')->label('Bakken terug')->numeric()->minValue(0),
                TextInput::make('crates_damaged')->label('Bakken beschadigd')->numeric()->minValue(0)->default(0),
            ]),
            Section::make('Notities')->columns(2)->schema([
                Textarea::make('details')->label('Opmerkingen van de aanvrager')->rows(3),
                Textarea::make('admin_notes')->label('Interne notities')->rows(3),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Uitlening')->columns(4)->schema([
                TextEntry::make('reference')->label('Referentie')->copyable(),
                TextEntry::make('status')->label('Status')->badge(),
                TextEntry::make('period')->label('Periode')->state(fn (CupLoan $record): string => $record->starts_on->format('d/m/Y').' - '.$record->ends_on->format('d/m/Y')),
                TextEntry::make('event_name')->label('Evenement')->placeholder('-'),
                TextEntry::make('cups')->label('Bekers'),
                TextEntry::make('crates')->label('Bakken'),
                TextEntry::make('fees')->label('Tarieven')->state(fn (CupLoan $record): string => euro($record->rental_fee).' huur, '.euro($record->cup_fee).'/beker, '.euro($record->crate_fee).'/bak'),
                TextEntry::make('available')->label('Vrij in deze periode')->state(function (CupLoan $record): string {
                    $available = app(CupInventory::class)->available($record->starts_on, $record->ends_on, $record->id);

                    return ($available['cups'] ?? '∞').' bekers, '.($available['crates'] ?? '∞').' bakken';
                }),
            ]),
            Section::make('Huurder')->columns(3)->schema([
                TextEntry::make('organisation')->label('Organisatie'),
                TextEntry::make('representative')->label('Vertegenwoordiger'),
                TextEntry::make('vat_number')->label('Ondernemingsnr.')->placeholder('-'),
                TextEntry::make('email')->label('E-mail')->copyable(),
                TextEntry::make('phone')->label('Telefoon'),
                TextEntry::make('address')->label('Adres')->state(fn (CupLoan $record): string => $record->address()),
            ]),
            Section::make('Opvolging')->columns(4)->schema([
                TextEntry::make('contract.status')->label('Contract')->badge()->placeholder('Nog niet verstuurd'),
                TextEntry::make('picked_up_at')->label('Opgehaald')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('returned_at')->label('Teruggebracht')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('invoice.number')->label('Factuur')->placeholder('-')
                    ->state(fn (CupLoan $record): ?string => $record->invoice?->displayNumber())
                    ->url(fn (CupLoan $record): ?string => $record->invoice ? InvoiceResource::getUrl('view', ['record' => $record->invoice]) : null),
                TextEntry::make('return')->label('Retour')->columnSpanFull()->visible(fn (CupLoan $record): bool => $record->cups_returned !== null)
                    ->state(fn (CupLoan $record): string => "{$record->cups_returned} van {$record->cups} bekers terug ({$record->cups_dirty} vuil/kapot), {$record->crates_returned} van {$record->crates} bakken terug ({$record->crates_damaged} beschadigd). Aan te rekenen: {$record->cupsCharged()} bekers, {$record->cratesCharged()} bakken."),
                TextEntry::make('details')->label('Opmerkingen aanvrager')->placeholder('-')->columnSpan(2),
                TextEntry::make('admin_notes')->label('Interne notities')->placeholder('-')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('reference')->label('Ref.')->searchable(),
                TextColumn::make('starts_on')->label('Ophalen')->date('D d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Terug')->date('D d/m/Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('organisation')->label('Organisatie')->description(fn (CupLoan $record): ?string => $record->event_name)->searchable(['organisation', 'representative', 'event_name']),
                TextColumn::make('cups')->label('Bekers')->numeric(),
                TextColumn::make('crates')->label('Bakken')->numeric(),
                TextColumn::make('contract.status')->label('Contract')->badge()->placeholder('-')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(CupLoanStatus::class)->multiple(),
                Filter::make('current')->label('Lopend of komend')->default()
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereDate('ends_on', '>=', today())->orWhere('status', CupLoanStatus::PickedUp))),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /**
     * @return list<Action|ActionGroup>
     */
    public static function lifecycleActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Bevestigen')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (CupLoan $record): bool => $record->status === CupLoanStatus::Requested)
                ->action(function (CupLoan $record): void {
                    $problem = app(CupInventory::class)->problem($record->starts_on, $record->ends_on, $record->cups, $record->crates, $record->id);

                    if ($problem) {
                        Notification::make()->danger()->title('Niet genoeg materiaal')->body($problem)->persistent()->send();

                        return;
                    }

                    $record->update(['status' => CupLoanStatus::Confirmed]);
                    Notification::make()->success()->title('Bevestigd. Verstuur nu het contract.')->send();
                }),
            Action::make('reject')
                ->label('Weigeren')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (CupLoan $record): bool => $record->status === CupLoanStatus::Requested)
                ->action(fn (CupLoan $record) => $record->update(['status' => CupLoanStatus::Rejected])),
            ContractActions::group(),
            Action::make('pickUp')
                ->label('Opgehaald')
                ->icon(Heroicon::OutlinedTruck)
                ->color('warning')
                ->visible(fn (CupLoan $record): bool => $record->status === CupLoanStatus::Confirmed)
                ->modalDescription(fn (CupLoan $record): ?string => $record->contract?->isSigned() ? null : 'Let op: het contract is nog niet ondertekend.')
                ->schema([
                    TextInput::make('cups')->label('Meegegeven bekers')->numeric()->minValue(1)->required()->default(fn (CupLoan $record): int => $record->cups),
                    TextInput::make('crates')->label('Meegegeven bakken')->numeric()->minValue(0)->required()->default(fn (CupLoan $record): int => $record->crates),
                ])
                ->action(fn (CupLoan $record, array $data) => $record->update([...$data, 'status' => CupLoanStatus::PickedUp, 'picked_up_at' => now()])),
            Action::make('return')
                ->label('Teruggebracht')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('success')
                ->visible(fn (CupLoan $record): bool => $record->status === CupLoanStatus::PickedUp)
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('cups_returned')->label('Bekers terug')->numeric()->minValue(0)->required()->default(fn (CupLoan $record): int => $record->cups),
                        TextInput::make('cups_dirty')->label('Waarvan vuil of kapot')->numeric()->minValue(0)->default(0)->required(),
                        TextInput::make('crates_returned')->label('Bakken terug')->numeric()->minValue(0)->required()->default(fn (CupLoan $record): int => $record->crates),
                        TextInput::make('crates_damaged')->label('Bakken beschadigd')->numeric()->minValue(0)->default(0)->required(),
                    ]),
                ])
                ->action(function (CupLoan $record, array $data): void {
                    $record->update([...$data, 'status' => CupLoanStatus::Returned, 'returned_at' => now()]);
                    Notification::make()->success()->title('Retour geregistreerd')
                        ->body("Aan te rekenen: {$record->cupsCharged()} bekers en {$record->cratesCharged()} bakken. Maak nu de factuur.")->send();
                }),
            Action::make('invoice')
                ->label(fn (CupLoan $record): string => $record->invoice_id ? 'Factuur openen' : 'Factuur maken')
                ->icon(Heroicon::OutlinedDocumentCurrencyEuro)
                ->color('primary')
                ->visible(fn (CupLoan $record): bool => in_array($record->status, [CupLoanStatus::PickedUp, CupLoanStatus::Returned], true) && auth()->user()->hasPermission(Permissions::INVOICES))
                ->action(function (CupLoan $record) {
                    try {
                        $invoice = $record->invoice ?? app(Invoicing::class)->forCupLoan($record);

                        return redirect(InvoiceResource::getUrl($invoice->isDraft() ? 'edit' : 'view', ['record' => $invoice]));
                    } catch (Throwable $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
            Action::make('cancel')
                ->label('Annuleren')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (CupLoan $record): bool => $record->status === CupLoanStatus::Confirmed)
                ->action(fn (CupLoan $record) => $record->update(['status' => CupLoanStatus::Cancelled])),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCupLoans::route('/'),
            'create' => CreateCupLoan::route('/create'),
            'view' => ViewCupLoan::route('/{record}'),
            'edit' => EditCupLoan::route('/{record}/edit'),
        ];
    }
}
