<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Enums\ProductCategory;
use App\Models\Product;
use App\Models\ProductServing;
use App\Support\Permissions;
use App\Support\Pricing;
use App\Support\PricingExport;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use UnitEnum;

/**
 * Factorberekening: purchase price per package against the selling price per serving, the factor that gives,
 * and the price recommended for the target factor. Prices can be edited in the table.
 */
class PricingPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Factorberekening';

    protected static ?string $title = 'Factorberekening';

    protected static ?string $slug = 'pricing';

    protected string $view = 'filament.pages.pricing';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::PRICING);
    }

    public function pricing(): Pricing
    {
        return app(Pricing::class);
    }

    public static function money(?float $value, int $decimals = 2): string
    {
        return $value === null ? '-' : '€ '.number_format($value, $decimals, ',', '.');
    }

    public static function factorLabel(?float $value): string
    {
        return $value === null ? '-' : number_format($value, 2, ',', '.');
    }

    public function table(Table $table): Table
    {
        $pricing = $this->pricing();
        $factorColor = fn (?float $factor, ProductServing $record): ?string => $factor === null ? 'gray' : ($factor < $pricing->targetFactor($record->product->category) - 0.005 ? 'danger' : 'success');

        return $table
            ->query(Pricing::query())
            ->defaultSort(fn (Builder $query) => $query->orderBy('products.sort')->orderBy('products.id')->orderBy('product_servings.sort')->orderBy('product_servings.id'))
            ->defaultGroup(Group::make('product.category')->label('Categorie')->getTitleFromRecordUsing(fn (ProductServing $record): string => $record->product->category->getLabel()))
            ->paginated(false)
            ->striped()
            ->columns([
                TextColumn::make('product.name')->label('Product')->description(fn (ProductServing $record): string => $record->label)->searchable(),
                TextColumn::make('product.package_price')->label('Verpakking excl.')->state(fn (ProductServing $record): ?float => $record->product->package_price !== null ? (float) $record->product->package_price : null)->formatStateUsing(fn (?float $state): string => self::money($state))->placeholder('-'),
                TextColumn::make('product.vat_rate')->label('Btw')->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', ''), '0'), ',').'%'),
                TextColumn::make('package_incl')->label('Verpakking incl.')->state(fn (ProductServing $record): ?float => $record->product->packagePriceInclVat())->formatStateUsing(fn (?float $state): string => self::money($state))->placeholder('-'),
                TextColumn::make('servings_per_package')->label('Aantal')->tooltip('Aantal porties uit één verpakking')->formatStateUsing(fn ($state): string => rtrim(rtrim(number_format((float) $state, 2, ',', ''), '0'), ',')),
                TextColumn::make('cost')->label('Kost/portie')->state(fn (ProductServing $record): ?float => $pricing->costPerServing($record))->formatStateUsing(fn (?float $state): string => self::money($state, 3))->placeholder('-'),
                TextInputColumn::make('price')->label('Verkoopprijs')->type('number')->step(0.05)->rules(['nullable', 'numeric', 'min:0'])->extraAttributes(['style' => 'min-width: 6rem']),
                TextColumn::make('factor')->label('Factor')->state(fn (ProductServing $record): ?float => $pricing->factor($record, $record->price))->formatStateUsing(fn (?float $state): string => self::factorLabel($state))->placeholder('-')->badge()->color(fn (?float $state, ProductServing $record): ?string => $factorColor($state, $record)),
                TextColumn::make('recommended')->label('Aanbevolen')->tooltip(fn (ProductServing $record): string => 'Kost per portie × '.self::factorLabel($pricing->targetFactor($record->product->category)).', afgerond naar boven')->state(fn (ProductServing $record): ?float => $pricing->recommendedPrice($record))->formatStateUsing(fn (?float $state): string => self::money($state))->placeholder('-')->weight('bold'),
                TextInputColumn::make('proposed_price')->label('Nieuwe prijs')->type('number')->step(0.05)->rules(['nullable', 'numeric', 'min:0'])->extraAttributes(['style' => 'min-width: 6rem']),
                TextColumn::make('proposed_factor')->label('Nieuwe factor')->state(fn (ProductServing $record): ?float => $record->proposed_price === null ? null : $pricing->factor($record, $record->proposed_price))->formatStateUsing(fn (?float $state): string => self::factorLabel($state))->placeholder('-')->badge()->color(fn (?float $state, ProductServing $record): ?string => $factorColor($state, $record)),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categorie')->options(ProductCategory::class)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $value) => $query->where('products.category', $value))),
                Filter::make('missing_cost')->label('Zonder aankoopprijs')
                    ->query(fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('products.package_price')->orWhere('products.package_price', '<=', 0))),
            ])
            ->headerActions([$this->addServingAction()])
            ->recordActions([$this->editServingAction(), DeleteAction::make()->iconButton()])
            ->toolbarActions([
                BulkAction::make('proposeSelected')
                    ->label('Aanbevolen prijs als nieuwe prijs')
                    ->icon(Heroicon::OutlinedSparkles)
                    ->action(function (Collection $records): void {
                        $count = $this->pricing()->proposeRecommended($records->load('product'));
                        Notification::make()->success()->title("{$count} nieuwe prijzen ingevuld")->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateHeading('Nog geen verkoopprijzen')
            ->emptyStateDescription('Voeg per product toe hoe het verkocht wordt (flesje, 25cl van het vat, glas wijn...).');
    }

    /**
     * @return array<int, mixed>
     */
    private function servingFields(): array
    {
        return [
            TextInput::make('label')->label('Verkocht als')->placeholder('bv. Flesje, 25cl, 50cl, Meter, Glas, Fles')->required()->maxLength(255),
            TextInput::make('servings_per_package')->label('Aantal per verpakking')->helperText('Bak van 24 = 24; vat van 50L = 185 × 25cl of 95 × 50cl; fles wijn = 4 glazen.')->numeric()->minValue(0.01)->required(),
            TextInput::make('price')->label('Verkoopprijs')->numeric()->minValue(0)->prefix('€'),
            TextInput::make('proposed_price')->label('Nieuwe prijs')->numeric()->minValue(0)->prefix('€'),
            Toggle::make('is_primary')->label('Dit is de toogprijs van het product')->helperText('De prijs komt dan ook in de prijslijst op de website en bij de verhuur.')->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function purchaseFields(): array
    {
        return [
            TextInput::make('package_price')->label('Aankoopprijs verpakking excl. btw')->numeric()->minValue(0)->prefix('€'),
            Select::make('vat_rate')->label('Btw')->options(['0' => '0%', '6' => '6%', '12' => '12%', '21' => '21%'])->required(),
            TextInput::make('units_per_package')->label('Stuks per verpakking')->numeric()->integer()->minValue(1),
        ];
    }

    private function addServingAction(): Action
    {
        return Action::make('addServing')
            ->label('Verkoopprijs toevoegen')
            ->icon(Heroicon::OutlinedPlus)
            ->modalWidth('2xl')
            ->schema([
                Select::make('product_id')->label('Product')->options(fn (): array => Product::query()->active()->get()->mapWithKeys(fn (Product $product): array => [$product->id => $product->label()])->all())->searchable()->required()->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $product = Product::find($state);
                        $set('package_price', $product?->package_price);
                        $set('vat_rate', $product ? (string) (int) $product->vat_rate : '21');
                        $set('units_per_package', $product?->units_per_package);
                        $set('servings_per_package', $product?->units_per_package ?: 1);
                    }),
                Section::make('Aankoop')->columns(3)->schema($this->purchaseFields()),
                Grid::make(2)->schema($this->servingFields()),
            ])
            ->fillForm(['vat_rate' => '21', 'label' => 'Stuk'])
            ->action(function (array $data): void {
                $product = Product::findOrFail($data['product_id']);
                $product->update(collect($data)->only(['package_price', 'vat_rate', 'units_per_package'])->all());
                $product->servings()->create(collect($data)->only(['label', 'servings_per_package', 'price', 'proposed_price', 'is_primary'])->all() + ['sort' => (int) $product->servings()->max('sort') + 1]);
                Notification::make()->success()->title('Verkoopprijs toegevoegd')->send();
            });
    }

    private function editServingAction(): Action
    {
        return Action::make('editServing')
            ->label('Bewerken')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->modalHeading(fn (ProductServing $record): string => $record->product->name.' - '.$record->label)
            ->modalWidth('2xl')
            ->fillForm(fn (ProductServing $record): array => [
                ...$record->only(['label', 'servings_per_package', 'price', 'proposed_price', 'is_primary']),
                'package_price' => $record->product->package_price,
                'vat_rate' => (string) (int) $record->product->vat_rate,
                'units_per_package' => $record->product->units_per_package,
            ])
            ->schema(fn (ProductServing $record): array => [
                Section::make('Aankoop')->description($record->product->servings()->count() > 1 ? 'Geldt voor alle verkoopprijzen van '.$record->product->name.'.' : null)->columns(3)->schema($this->purchaseFields()),
                Grid::make(2)->schema($this->servingFields()),
                Placeholder::make('hint')->hiddenLabel()->content(fn (): string => 'Doelfactor: '.self::factorLabel($this->pricing()->targetFactor($record->product->category))),
            ])
            ->action(function (ProductServing $record, array $data): void {
                $record->product->update(collect($data)->only(['package_price', 'vat_rate', 'units_per_package'])->all());
                $record->update(collect($data)->only(['label', 'servings_per_package', 'price', 'proposed_price', 'is_primary'])->all());
                Notification::make()->success()->title('Opgeslagen')->send();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('target')
                ->label(fn (): string => 'Doelfactor '.self::factorLabel($this->pricing()->targetFactor()))
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->color('gray')
                ->modalHeading('Doelfactor en afronding')
                ->modalDescription('Aanbevolen prijs = kost per portie incl. btw × doelfactor, naar boven afgerond. Een factor van 2 betekent dat de verkoopprijs het dubbele is van de aankoopprijs.')
                ->fillForm(fn (): array => (array) settings()->get('pricing'))
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('target_factor')->label('Doelfactor')->numeric()->minValue(1)->step(0.05)->required(),
                        Select::make('rounding')->label('Afronden naar boven op')->options(['0.05' => '€ 0,05', '0.1' => '€ 0,10', '0.25' => '€ 0,25', '0.5' => '€ 0,50', '1' => '€ 1,00'])->required(),
                    ]),
                    Section::make('Andere doelfactor per categorie')->description('Leeg = de algemene doelfactor.')->columns(3)->schema(array_map(
                        fn (ProductCategory $category) => TextInput::make('target_'.$category->value)->label($category->getLabel())->numeric()->minValue(1)->step(0.05),
                        ProductCategory::cases(),
                    )),
                ])
                ->action(function (array $data): void {
                    settings()->save(['pricing' => array_replace((array) settings()->get('pricing'), [
                        'target_factor' => (float) $data['target_factor'],
                        'rounding' => (float) $data['rounding'],
                        ...collect(ProductCategory::cases())->mapWithKeys(fn (ProductCategory $category): array => ['target_'.$category->value => filled($data['target_'.$category->value] ?? null) ? (float) $data['target_'.$category->value] : null])->all(),
                    ])]);
                    Notification::make()->success()->title('Doelfactor opgeslagen')->send();
                }),
            Action::make('proposeAll')
                ->label('Aanbevolen → nieuwe prijs')
                ->icon(Heroicon::OutlinedSparkles)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Vult bij elk product met een aankoopprijs de kolom "Nieuwe prijs" met de aanbevolen prijs. De huidige verkoopprijzen blijven ongewijzigd.')
                ->action(function (): void {
                    $count = $this->pricing()->proposeRecommended();
                    Notification::make()->success()->title("{$count} nieuwe prijzen ingevuld")->send();
                }),
            Action::make('apply')
                ->label('Nieuwe prijzen toepassen')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('De nieuwe prijzen worden de verkoopprijzen (ook de toogprijs in de prijslijst op de website en bij de verhuur). De kolom "Nieuwe prijs" wordt daarna leeggemaakt.')
                ->disabled(fn (): bool => ! ProductServing::query()->whereNotNull('proposed_price')->exists())
                ->action(function (): void {
                    $count = $this->pricing()->applyProposed();
                    Notification::make()->success()->title("{$count} prijzen aangepast")->send();
                }),
            Action::make('excel')
                ->label('Excel')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('success')
                ->action(fn (): BinaryFileResponse => response()->download(app(PricingExport::class)->xlsx(), 'factorberekening-'.now()->format('Y-m-d').'.xlsx')->deleteFileAfterSend()),
        ];
    }
}
