<?php

namespace App\Filament\Resources\PurchaseInvoices;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\NavigationGroup;
use App\Enums\PaymentMethod;
use App\Enums\StockMovementType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PurchaseInvoices\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\EditPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\Pages\ListPurchaseInvoices;
use App\Filament\Resources\PurchaseInvoices\Pages\ViewPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoices\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Support\Permissions;
use App\Support\Qr;
use App\Support\Stock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

/**
 * Incoming invoices: upload the scan, enter the amounts, pay (with a payment QR), book deliveries into stock.
 */
class PurchaseInvoiceResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PURCHASES;

    protected static ?string $model = PurchaseInvoice::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'inkomende factuur';

    protected static ?string $pluralModelLabel = 'inkomende facturen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reference';

    public static function getNavigationBadge(): ?string
    {
        $overdue = PurchaseInvoice::query()->overdue()->count();

        return $overdue > 0 ? (string) $overdue : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Vervallen, nog te betalen';
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'number', 'description', 'supplier.name'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(2)->schema([
                Select::make('supplier_id')
                    ->label('Leverancier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->createOptionForm(SupplierResource::fields())
                    ->live()
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                        $supplier = Supplier::query()->find($state);

                        if ($supplier?->default_category_id && blank($get('expense_category_id'))) {
                            $set('expense_category_id', $supplier->default_category_id);
                        }

                        self::fillDueDate($get, $set);
                    }),
                TextInput::make('number')
                    ->label('Factuurnummer leverancier')
                    ->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('supplier_id', $get('supplier_id')))
                    ->validationMessages(['unique' => 'Deze factuur van deze leverancier is al ingeboekt.']),
                Select::make('type')->label('Soort')->options(InvoiceType::class)->default(InvoiceType::Invoice)->required(),
                Select::make('expense_category_id')->label('Kostencategorie')->relationship('category', 'name', fn (Builder $query) => $query->active()),
                DatePicker::make('issue_date')->label('Factuurdatum')->default(today())->required()->native(false)->displayFormat('d/m/Y')
                    ->live()->afterStateUpdated(fn (Get $get, Set $set) => self::fillDueDate($get, $set)),
                DatePicker::make('due_date')->label('Vervaldatum')->native(false)->displayFormat('d/m/Y')->afterOrEqual('issue_date'),
                TextInput::make('description')->label('Omschrijving')->maxLength(255)->columnSpanFull()->placeholder('bv. drankleveringen oktober, elektriciteit Q3, DJ fuif ...'),
                Select::make('event_id')->label('Voor evenement')->relationship('event', 'title', fn (Builder $query) => $query->latest('starts_at'))
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->starts_at->format('d/m/Y').' - '.$record->title)
                    ->searchable()->preload()->helperText('Optioneel: om de kosten per evenement te zien.'),
                TextInput::make('payment_reference')->label('Mededeling voor de betaling')->maxLength(140)->placeholder('+++123/4567/89012+++'),
            ]),
            Section::make()->columnSpan(1)->schema([
                FileUpload::make('file_path')
                    ->label('Factuur (PDF of foto)')
                    ->disk('local')
                    ->directory('purchase-invoices/'.now()->format('Y'))
                    ->visibility('private')
                    ->storeFileNamesIn('file_name')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic'])
                    ->maxSize(15 * 1024)
                    ->openable(false)
                    ->downloadable(false),
                TextInput::make('subtotal')->label('Bedrag excl. btw')->numeric()->prefix('€')->default(0)->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $set('total', round((float) $get('subtotal') + (float) $get('vat_total'), 2))),
                TextInput::make('vat_total')->label('Btw')->numeric()->prefix('€')->default(0)->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $set('total', round((float) $get('subtotal') + (float) $get('vat_total'), 2))),
                TextInput::make('total')->label('Totaal incl. btw')->numeric()->prefix('€')->required()->minValue(0),
            ]),
            Textarea::make('notes')->label('Notities')->rows(2)->columnSpanFull(),
        ]);
    }

    private static function fillDueDate(Get $get, Set $set): void
    {
        if (blank($get('issue_date'))) {
            return;
        }

        $days = Supplier::query()->find($get('supplier_id'))?->payment_days ?? 30;
        $set('due_date', Carbon::parse($get('issue_date'))->addDays($days)->toDateString());
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()->columnSpan(2)->columns(3)->schema([
                TextEntry::make('supplier.name')->label('Leverancier'),
                TextEntry::make('number')->label('Nummer leverancier')->placeholder('-')->copyable(),
                TextEntry::make('type')->label('Soort')->badge(),
                TextEntry::make('issue_date')->label('Factuurdatum')->date('d/m/Y'),
                TextEntry::make('due_date')->label('Vervaldatum')->date('d/m/Y')->placeholder('-')->color(fn (PurchaseInvoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextEntry::make('category.name')->label('Categorie')->placeholder('-'),
                TextEntry::make('description')->label('Omschrijving')->placeholder('-')->columnSpan(2),
                TextEntry::make('event.title')->label('Evenement')->placeholder('-'),
                TextEntry::make('payment_reference')->label('Mededeling')->placeholder('-')->copyable(),
                TextEntry::make('supplier.iban')->label('IBAN leverancier')->placeholder('Niet ingevuld')->copyable(),
                TextEntry::make('stock_booked_at')->label('In stock geboekt')->dateTime('d/m/Y H:i')->placeholder('-'),
                TextEntry::make('notes')->label('Notities')->placeholder('-')->columnSpanFull(),
            ]),
            Section::make()->columnSpan(1)->schema([
                TextEntry::make('status')->label('Status')->badge()
                    ->state(fn (PurchaseInvoice $record): string => $record->isOverdue() ? 'Vervallen' : $record->status->getLabel())
                    ->color(fn (PurchaseInvoice $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor()),
                TextEntry::make('amounts')->label('Excl. btw + btw')->state(fn (PurchaseInvoice $record): string => euro($record->subtotal).' + '.euro($record->vat_total)),
                TextEntry::make('total')->label('Totaal')->money('EUR')->size('lg')->weight('bold'),
                TextEntry::make('balance')->label('Nog te betalen')->state(fn (PurchaseInvoice $record): float => max(0, $record->balance()))->money('EUR')
                    ->color(fn (PurchaseInvoice $record): string => $record->balance() > 0 ? 'danger' : 'success'),
            ]),
            Section::make('Factuur')->columnSpanFull()->visible(fn (PurchaseInvoice $record): bool => filled($record->file_path))->schema([
                ViewEntry::make('file')->hiddenLabel()->view('filament.purchase-invoice-file'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('issue_date', 'desc')
            ->columns([
                TextColumn::make('reference')->label('Ref.')->searchable()->sortable()->toggleable(),
                TextColumn::make('supplier.name')->label('Leverancier')->description(fn (PurchaseInvoice $record): ?string => $record->description)->searchable()->sortable(),
                TextColumn::make('number')->label('Nr. leverancier')->searchable()->toggleable(),
                TextColumn::make('issue_date')->label('Datum')->date('d/m/Y')->sortable(),
                TextColumn::make('due_date')->label('Vervalt')->date('d/m/Y')->sortable()->placeholder('-')
                    ->color(fn (PurchaseInvoice $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('category.name')->label('Categorie')->badge()->color('gray')->placeholder('-')->toggleable(),
                TextColumn::make('event.title')->label('Evenement')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')->label('Totaal')->money('EUR')->sortable()
                    ->state(fn (PurchaseInvoice $record): float => $record->isCreditNote() ? -1 * (float) $record->total : (float) $record->total)
                    ->summarize(Summarizer::make()->label('')->money('EUR')
                        ->using(fn (\Illuminate\Database\Query\Builder $query): float => (float) $query->sum(DB::raw("CASE WHEN type = 'credit_note' THEN -total ELSE total END")))),
                TextColumn::make('open')->label('Open')->state(fn (PurchaseInvoice $record): float => $record->isCreditNote() ? 0 : max(0, $record->balance()))->money('EUR'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (PurchaseInvoice $record): string => $record->isOverdue() ? 'Vervallen' : $record->status->getLabel())
                    ->color(fn (PurchaseInvoice $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor()),
                TextColumn::make('file_path')->label('')->formatStateUsing(fn (): string => '📎')->placeholder('')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('supplier')->label('Leverancier')->relationship('supplier', 'name')->searchable()->preload(),
                SelectFilter::make('category')->label('Categorie')->relationship('category', 'name')->multiple()->preload(),
                SelectFilter::make('event')->label('Evenement')->relationship('event', 'title')->searchable()->preload(),
                SelectFilter::make('status')->label('Status')->options(InvoiceStatus::class)->multiple(),
                Filter::make('period')
                    ->schema([DatePicker::make('from')->label('Factuurdatum van')->native(false), DatePicker::make('until')->label('tot')->native(false)])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('issue_date', '<=', $date))),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()]);
    }

    /**
     * @return list<Action>
     */
    public static function invoiceActions(): array
    {
        return [
            Action::make('registerPayment')
                ->label('Betaald')
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (PurchaseInvoice $record): bool => ! $record->isCreditNote() && $record->balance() > 0 && $record->status !== InvoiceStatus::Cancelled)
                ->schema(fn (PurchaseInvoice $record): array => [
                    DatePicker::make('paid_on')->label('Datum')->default(today())->required()->native(false)->displayFormat('d/m/Y'),
                    TextInput::make('amount')->label('Bedrag')->numeric()->prefix('€')->default($record->balance())->required(),
                    Select::make('method')->label('Hoe')->options(PaymentMethod::class)->default(PaymentMethod::Bank)->required(),
                    TextInput::make('reference')->label('Opmerking'),
                ])
                ->action(function (PurchaseInvoice $record, array $data): void {
                    $record->payments()->create([...$data, 'user_id' => auth()->id()]);
                    Notification::make()->success()->title('Betaling geregistreerd')->body('Status: '.$record->refresh()->status->getLabel())->send();
                }),
            Action::make('payQr')
                ->label('Betaal-QR')
                ->icon(Heroicon::OutlinedQrCode)
                ->color('gray')
                ->visible(fn (PurchaseInvoice $record): bool => ! $record->isCreditNote() && $record->balance() > 0 && filled($record->supplier->iban))
                ->modalHeading('Scan met je bankapp')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten')
                ->modalContent(fn (PurchaseInvoice $record): HtmlString => new HtmlString(
                    '<div style="text-align:center;"><img src="'.Qr::pngDataUri(Qr::epcPayload($record->supplier->name, (string) $record->supplier->iban, $record->supplier->bic, $record->balance(), (string) ($record->payment_reference ?: trim($record->number.' '.$record->supplier->name))), 7).'" alt="" style="margin:0 auto;width:16rem;height:16rem;">'
                    .'<p style="margin-top:.75rem;">'.e(euro($record->balance())).' aan '.e($record->supplier->name).'<br>'.e((string) $record->supplier->iban).'<br>'.e((string) ($record->payment_reference ?: $record->number)).'</p>'
                    .'<p style="opacity:.6;font-size:.85rem;margin-top:.5rem;">Registreer de betaling daarna met de knop "Betaald".</p></div>'
                )),
            Action::make('bookStock')
                ->label(fn (PurchaseInvoice $record): string => $record->stock_booked_at ? 'Opnieuw in stock boeken' : 'In stock boeken')
                ->icon(Heroicon::OutlinedArchiveBoxArrowDown)
                ->color('gray')
                ->visible(fn (PurchaseInvoice $record): bool => ! $record->isCreditNote() && (bool) auth()->user()?->hasPermission(Permissions::STOCK))
                ->modalHeading('Levering in stock boeken')
                ->modalDescription(fn (PurchaseInvoice $record): string => $record->stock_booked_at
                    ? 'Let op: deze factuur werd al op '.$record->stock_booked_at->format('d/m/Y').' in stock geboekt. Boek enkel wat nog ontbreekt.'
                    : 'Voeg de geleverde producten toe. Met een aankoopprijs per stuk wordt ook de aankoopprijs van het product bijgewerkt.')
                ->modalWidth('3xl')
                ->schema([
                    Repeater::make('items')
                        ->hiddenLabel()
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Product toevoegen')
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('product_id')->label('Product')->required()->searchable()->columnSpan(2)
                                    ->options(fn (): array => Product::query()->tracked()->orderBy('name')->get()->mapWithKeys(fn (Product $product): array => [$product->id => $product->label()])->all()),
                                TextInput::make('packages')->label('Bakken/verpakkingen')->numeric()->minValue(0),
                                TextInput::make('units')->label('Losse stuks')->numeric()->minValue(0),
                                TextInput::make('unit_price')->label('Aankoopprijs/stuk')->numeric()->prefix('€')->columnSpan(2),
                            ]),
                        ]),
                ])
                ->action(function (PurchaseInvoice $record, array $data): void {
                    $booked = DB::transaction(function () use ($record, $data): int {
                        $lines = 0;

                        foreach ($data['items'] as $item) {
                            $product = Product::query()->findOrFail($item['product_id']);
                            $quantity = (int) ($item['units'] ?? 0) + (int) ($item['packages'] ?? 0) * max(1, (int) $product->units_per_package);

                            if ($quantity <= 0) {
                                continue;
                            }

                            app(Stock::class)->move($product, StockMovementType::Purchase, $quantity, 'Factuur '.($record->number ?: $record->reference).' - '.$record->supplier->name, $record);

                            if (filled($item['unit_price'] ?? null)) {
                                $product->update(['purchase_price' => $item['unit_price']]);
                            }

                            $lines++;
                        }

                        $record->forceFill(['stock_booked_at' => now()])->saveQuietly();

                        return $lines;
                    });

                    Notification::make()->success()->title("{$booked} producten in stock geboekt")->send();
                }),
            Action::make('file')
                ->label('Factuur openen')
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->visible(fn (PurchaseInvoice $record): bool => filled($record->file_path))
                ->url(fn (PurchaseInvoice $record): string => route('purchase-invoices.file', $record), shouldOpenInNewTab: true),
            Action::make('cancel')
                ->label('Annuleren')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Bv. bij een foutieve factuur die de leverancier vervangt. De factuur blijft bewaard maar telt niet meer mee als te betalen.')
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === InvoiceStatus::Open && (float) $record->paid_total === 0.0)
                ->action(fn (PurchaseInvoice $record) => $record->forceFill(['status' => InvoiceStatus::Cancelled])->save()),
        ];
    }

    public static function getRelations(): array
    {
        return [PaymentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseInvoices::route('/'),
            'create' => CreatePurchaseInvoice::route('/create'),
            'view' => ViewPurchaseInvoice::route('/{record}'),
            'edit' => EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
