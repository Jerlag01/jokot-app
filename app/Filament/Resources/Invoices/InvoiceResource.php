<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Resources\Invoices\Pages\EditInvoice;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Resources\Invoices\RelationManagers\PaymentsRelationManager;
use App\Models\Invoice;
use App\Models\Product;
use App\Support\Billit\Billit;
use App\Support\Billit\BillitClient;
use App\Support\Invoicing;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

/**
 * Invoicing like Billit: drafts, finalising (number + structured reference), PDF, e-mail, reminders,
 * payments, credit notes.
 */
class InvoiceResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::INVOICES;

    protected static ?string $model = Invoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'factuur';

    protected static ?string $pluralModelLabel = 'facturen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'number';

    public static function getNavigationBadge(): ?string
    {
        $overdue = Invoice::query()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Vervallen';
    }

    /**
     * Only drafts can be edited; a finalised invoice is corrected with a credit note.
     */
    public static function canEdit(Model $record): bool
    {
        return static::userCan() && $record->isDraft();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && $record->isDraft();
    }

    /**
     * @return array<string, string>
     */
    public static function vatRates(): array
    {
        return ['0' => '0%', '6' => '6%', '12' => '12%', '21' => '21%'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                Select::make('customer_id')
                    ->label('Klant')
                    ->relationship('customer', 'name')
                    ->searchable(['name', 'contact_name', 'email'])
                    ->preload()
                    ->required()
                    ->createOptionForm(CustomerResource::fields())
                    ->default(fn (): ?int => request()->integer('customer') ?: null)
                    ->columnSpanFull(),
                TextInput::make('subject')->label('Onderwerp')->maxLength(255)->columnSpanFull(),
            ]),
            Section::make()->columnSpan(1)->schema([
                Select::make('type')->label('Soort')->options(InvoiceType::class)->default(InvoiceType::Invoice)->required()->disabledOn('edit'),
                DatePicker::make('issue_date')->label('Factuurdatum')->default(today())->required()->native(false)->displayFormat('d/m/Y')
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set) => $state ? $set('due_date', Carbon::parse($state)->addDays((int) settings()->get('invoice_payment_days', 30))->toDateString()) : null),
                DatePicker::make('due_date')->label('Vervaldatum')->default(today()->addDays((int) settings()->get('invoice_payment_days', 30)))->required()->native(false)->displayFormat('d/m/Y')->afterOrEqual('issue_date'),
            ]),
            Section::make('Lijnen')->columnSpanFull()->schema([
                Repeater::make('lines')
                    ->hiddenLabel()
                    ->relationship('lines')
                    ->orderColumn('sort')
                    ->defaultItems(1)
                    ->addActionLabel('Lijn toevoegen')
                    ->schema([
                        Grid::make(12)->schema([
                            Select::make('product_id')
                                ->label('Product')
                                ->options(fn (): array => Product::query()->active()->pluck('name', 'id')->all())
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if ($product = Product::query()->find($state)) {
                                        $set('description', $product->name);
                                        $set('unit_price', (float) ($product->rental_price ?? $product->bar_price ?? 0));
                                    }
                                })
                                ->columnSpan(3),
                            TextInput::make('description')->label('Omschrijving')->required()->maxLength(255)->columnSpan(4),
                            TextInput::make('quantity')->label('Aantal')->numeric()->default(1)->required()->live(onBlur: true)->columnSpan(1),
                            TextInput::make('unit_price')->label('Prijs')->numeric()->prefix('€')->required()->live(onBlur: true)->columnSpan(2),
                            Select::make('vat_rate')->label('Btw')->options(self::vatRates())->default(fn (): string => (string) (int) settings()->get('default_vat_rate', 0))->required()->selectablePlaceholder(false)->columnSpan(1),
                            TextInput::make('line_preview')->label('Totaal')->disabled()->dehydrated(false)
                                ->formatStateUsing(fn (Get $get): string => euro((float) $get('quantity') * (float) $get('unit_price')))
                                ->columnSpan(1),
                        ]),
                    ]),
            ]),
            Textarea::make('notes')->label('Opmerking op de factuur')->rows(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(3)->schema([
                TextEntry::make('customer.name')->label('Klant')
                    ->url(fn (Invoice $record): string => CustomerResource::getUrl('index', ['tableSearch' => $record->customer->name])),
                TextEntry::make('subject')->label('Onderwerp')->placeholder('-')->columnSpan(2),
                TextEntry::make('issue_date')->label('Datum')->date('d/m/Y'),
                TextEntry::make('due_date')->label('Vervaldatum')->date('d/m/Y')->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextEntry::make('structured_reference')->label('Mededeling')->placeholder('-')->copyable(),
                TextEntry::make('creditedInvoice.number')->label('Crediteert')->placeholder('-')
                    ->url(fn (Invoice $record): ?string => $record->creditedInvoice ? self::getUrl('view', ['record' => $record->creditedInvoice]) : null),
                TextEntry::make('sent_at')->label('Verstuurd')->dateTime('d/m/Y H:i')->placeholder('Nog niet'),
                TextEntry::make('reminded_at')->label('Herinnering')->dateTime('d/m/Y H:i')->placeholder('-'),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('status')->label('Status')->badge()
                    ->state(fn (Invoice $record): string => $record->isOverdue() ? 'Vervallen' : $record->status->getLabel())
                    ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor()),
                TextEntry::make('total')->label('Totaal')->money('EUR')->size('lg')->weight('bold'),
                TextEntry::make('paid_total')->label('Betaald / gecrediteerd')->money('EUR'),
                TextEntry::make('balance')->label('Openstaand')->state(fn (Invoice $record): float => $record->balance())->money('EUR')
                    ->color(fn (Invoice $record): ?string => $record->balance() > 0 ? 'danger' : 'success'),
            ]),
            Section::make('Billit')->columnSpanFull()->columns(4)->visible(fn (Invoice $record): bool => (bool) $record->billit_order_id || filled($record->billit_error))->schema([
                TextEntry::make('billit_order_id')->label('Billit-order')->placeholder('-'),
                TextEntry::make('billit_transport')->label('Afgeleverd via')->placeholder('-')
                    ->state(fn (Invoice $record): ?string => $record->billit_transport ? $record->billit_transport.($record->billit_recipient_provider ? ' ('.$record->billit_recipient_provider.')' : '') : null),
                TextEntry::make('billit_delivery_status')->label('Aflevering')->badge()->placeholder('-')
                    ->color(fn (?string $state): string => match ($state) {
                        'Delivered' => 'success', 'Error' => 'danger', default => 'warning'
                    }),
                TextEntry::make('billit_synced_at')->label('Laatst gecontroleerd')->since()->placeholder('-'),
                TextEntry::make('billit_delivery_info')->label('Info')->placeholder('-')->columnSpan(2),
                TextEntry::make('billit_error')->label('Fout')->placeholder('-')->color('danger')->columnSpan(2),
            ]),
            Section::make('Lijnen')->columnSpanFull()->schema([
                RepeatableEntry::make('lines')->hiddenLabel()->table([
                    TableColumn::make('Omschrijving'),
                    TableColumn::make('Aantal'),
                    TableColumn::make('Prijs'),
                    TableColumn::make('Btw'),
                    TableColumn::make('Totaal excl.'),
                ])->schema([
                    TextEntry::make('description'),
                    TextEntry::make('quantity')->numeric(locale: 'nl'),
                    TextEntry::make('unit_price')->money('EUR'),
                    TextEntry::make('vat_rate')->suffix('%')->numeric(),
                    TextEntry::make('line_total')->money('EUR'),
                ]),
                TextEntry::make('totals')->hiddenLabel()->alignEnd()
                    ->state(fn (Invoice $record): string => 'Subtotaal '.euro($record->subtotal).' + btw '.euro($record->vat_total).' = '.euro($record->total)),
                TextEntry::make('notes')->label('Opmerking')->placeholder('-'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Nummer')->placeholder('Concept')->searchable()->sortable(),
                TextColumn::make('type')->label('Soort')->badge()->toggleable(),
                TextColumn::make('customer.name')->label('Klant')->searchable()->sortable(),
                TextColumn::make('subject')->label('Onderwerp')->limit(40)->toggleable(),
                TextColumn::make('issue_date')->label('Datum')->date('d/m/Y')->sortable(),
                TextColumn::make('due_date')->label('Vervalt')->date('d/m/Y')->sortable()
                    ->color(fn (Invoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('total')->label('Totaal')->money('EUR')->sortable()->summarize(Sum::make()->money('EUR')->label('')),
                TextColumn::make('balance')->label('Open')->state(fn (Invoice $record): float => $record->isCreditNote() ? 0 : $record->balance())->money('EUR'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Invoice $record): string => $record->isOverdue() ? 'Vervallen' : $record->status->getLabel())
                    ->color(fn (Invoice $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor()),
                TextColumn::make('billit_delivery_status')->label('Billit')->badge()->placeholder('-')->toggleable()
                    ->state(fn (Invoice $record): ?string => $record->billit_order_id ? trim(($record->billit_transport ?? '').' '.($record->billit_delivery_status ?? '')) : null)
                    ->color(fn (Invoice $record): string => match ($record->billit_delivery_status) {
                        'Delivered' => 'success', 'Error' => 'danger', default => 'gray'
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(InvoiceStatus::class)->multiple(),
                SelectFilter::make('type')->label('Soort')->options(InvoiceType::class),
                SelectFilter::make('customer')->label('Klant')->relationship('customer', 'name')->searchable()->preload(),
            ])
            ->recordActions([
                Action::make('pdf')->label('PDF')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                    ->action(fn (Invoice $record) => self::downloadPdf($record)),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function downloadPdf(Invoice $invoice): StreamedResponse
    {
        $invoicing = app(Invoicing::class);
        $pdf = $invoicing->pdf($invoice)->output();

        return response()->streamDownload(fn () => print ($pdf), $invoicing->filename($invoice), ['Content-Type' => 'application/pdf']);
    }

    /**
     * Header actions on the invoice page, in the order of the invoice's life.
     *
     * @return list<Action>
     */
    public static function lifecycleActions(): array
    {
        $notifyFailure = fn (Throwable $exception) => Notification::make()->danger()->title('Mislukt')->body($exception->getMessage())->persistent()->send();

        return [
            Action::make('finalize')
                ->label('Definitief maken')
                ->icon(Heroicon::OutlinedLockClosed)
                ->requiresConfirmation()
                ->modalDescription('De factuur krijgt een nummer en kan daarna niet meer gewijzigd worden (enkel nog gecrediteerd).')
                ->visible(fn (Invoice $record): bool => $record->isDraft())
                ->action(function (Invoice $record) use ($notifyFailure): void {
                    try {
                        $record->finalize();
                        Notification::make()->success()->title('Factuur '.$record->number.' aangemaakt')->send();
                    } catch (Throwable $exception) {
                        $notifyFailure($exception);
                    }
                }),
            Action::make('send')
                ->label(fn (Invoice $record): string => $record->sent_at ? 'Opnieuw versturen' : 'Versturen per e-mail')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('info')
                ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Cancelled)
                ->modalDescription(fn (Invoice $record): ?string => $record->isDraft() ? 'Het concept wordt eerst definitief gemaakt.' : null)
                ->schema([TextInput::make('to')->label('Naar')->email()->required()->default(fn (Invoice $record): ?string => $record->customer->email)])
                ->action(function (Invoice $record, array $data) use ($notifyFailure): void {
                    try {
                        app(Invoicing::class)->send($record, $data['to']);
                        Notification::make()->success()->title('Verstuurd naar '.$data['to'])->send();
                    } catch (Throwable $exception) {
                        $notifyFailure($exception);
                    }
                }),
            Action::make('billitSend')
                ->label(fn (Invoice $record): string => $record->billit_sent_at ? 'Opnieuw via Billit' : 'Via Billit versturen')
                ->icon(Heroicon::OutlinedCloudArrowUp)
                ->color('primary')
                ->visible(fn (Invoice $record): bool => $record->status !== InvoiceStatus::Cancelled && app(BillitClient::class)->isConfigured())
                ->modalDescription(fn (Invoice $record): string => ($record->isDraft() ? 'Het concept wordt eerst definitief gemaakt. ' : '')
                    .'Billit levert de factuur af met onze PDF en gestructureerde mededeling. Peppol bereikt ook Doccle-gebruikers die via Doccle op Peppol staan.')
                ->schema([
                    Select::make('transport')->label('Afleveren via')->options(Billit::TRANSPORTS)
                        ->default(fn (): string => (string) settings()->get('billit.transport', 'auto'))->required(),
                ])
                ->action(function (Invoice $record, array $data) use ($notifyFailure): void {
                    try {
                        $transport = app(Billit::class)->send($record, $data['transport']);
                        $record->refresh();
                        Notification::make()->success()->title('Via Billit verstuurd ('.$transport.')')
                            ->body($record->billit_recipient_provider ? 'Peppol-ontvanger bij: '.$record->billit_recipient_provider : null)->send();
                    } catch (Throwable $exception) {
                        $notifyFailure($exception);
                    }
                }),
            Action::make('billitSync')
                ->label('Billit-status')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (Invoice $record): bool => (bool) $record->billit_order_id && app(BillitClient::class)->isConfigured())
                ->action(function (Invoice $record) use ($notifyFailure): void {
                    try {
                        $record = app(Billit::class)->sync($record);
                        Notification::make()->title('Aflevering: '.($record->billit_delivery_status ?? 'onbekend'))->body('Status: '.$record->status->getLabel())->send();
                    } catch (Throwable $exception) {
                        $notifyFailure($exception);
                    }
                }),
            Action::make('remind')
                ->label('Herinnering')
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('warning')
                ->visible(fn (Invoice $record): bool => $record->isOverdue())
                ->schema([TextInput::make('to')->label('Naar')->email()->required()->default(fn (Invoice $record): ?string => $record->customer->email)])
                ->action(function (Invoice $record, array $data) use ($notifyFailure): void {
                    try {
                        app(Invoicing::class)->send($record, $data['to'], reminder: true);
                        Notification::make()->success()->title('Herinnering verstuurd')->send();
                    } catch (Throwable $exception) {
                        $notifyFailure($exception);
                    }
                }),
            Action::make('registerPayment')
                ->label('Betaling')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (Invoice $record): bool => ! $record->isDraft() && ! $record->isCreditNote() && $record->balance() > 0 && $record->status !== InvoiceStatus::Cancelled)
                ->schema(fn (Invoice $record): array => [
                    DatePicker::make('paid_on')->label('Datum')->default(today())->required()->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('amount')->label('Bedrag')->numeric()->prefix('€')->default($record->balance())->required(),
                    Select::make('method')->label('Hoe')->options(PaymentMethod::class)->default(PaymentMethod::Bank)->required(),
                    TextInput::make('reference')->label('Referentie / opmerking'),
                ])
                ->action(function (Invoice $record, array $data): void {
                    $record->payments()->create([...$data, 'user_id' => auth()->id()]);
                    Notification::make()->success()->title('Betaling geregistreerd')->body('Status: '.$record->refresh()->status->getLabel())->send();
                }),
            Action::make('creditNote')
                ->label('Crediteren')
                ->icon(Heroicon::OutlinedReceiptRefund)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Er wordt een concept-creditnota met dezelfde lijnen gemaakt. Pas ze aan voor een gedeeltelijke creditering en maak ze daarna definitief.')
                ->visible(fn (Invoice $record): bool => ! $record->isDraft() && ! $record->isCreditNote() && $record->status !== InvoiceStatus::Cancelled)
                ->action(fn (Invoice $record) => redirect(self::getUrl('edit', ['record' => app(Invoicing::class)->creditNoteFor($record)]))),
            Action::make('pdf')
                ->label('PDF')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (Invoice $record) => self::downloadPdf($record)),
        ];
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
            'edit' => EditInvoice::route('/{record}/edit'),
        ];
    }
}
