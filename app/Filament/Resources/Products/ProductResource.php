<?php

namespace App\Filament\Resources\Products;

use App\Enums\NavigationGroup;
use App\Enums\ProductCategory;
use App\Enums\StockMovementType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use App\Support\Permissions;
use App\Support\Stock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Drinks and snacks: prices, rental order list and stock.
 */
class ProductResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PRODUCTS;

    protected static ?string $model = Product::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'product';

    protected static ?string $pluralModelLabel = 'producten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $low = Product::query()->lowStock()->count();

        return $low > 0 ? (string) $low : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Onder minimumstock';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(255)->columnSpan(2),
            Select::make('category')->label('Categorie')->options(ProductCategory::class)->default(ProductCategory::Soft)->required(),
            TextInput::make('package')->label('Verpakking')->placeholder('bv. bak van 24 flesjes')->maxLength(255),
            TextInput::make('units_per_package')->label('Stuks per verpakking')->numeric()->minValue(1),
            Toggle::make('is_active')->label('Actief')->default(true)->inline(false),
            Section::make('Prijzen')->columns(3)->columnSpanFull()->schema([
                TextInput::make('bar_price')->label('Toogprijs')->numeric()->prefix('€')->helperText('Prijs per stuk aan de toog.'),
                TextInput::make('rental_price')->label('Prijs voor huurders')->numeric()->prefix('€')->helperText('Komt in het contract en op de factuur na de telling.'),
                TextInput::make('purchase_price')->label('Aankoopprijs')->numeric()->prefix('€')->helperText('Voor de stockwaarde.'),
            ]),
            Section::make('Factorberekening')
                ->description('Aankoopprijs van één verpakking en hoe het product verkocht wordt. Zie Stock → Factorberekening.')
                ->columns(3)
                ->columnSpanFull()
                ->collapsed(fn (?Product $record): bool => $record !== null && $record->package_price === null)
                ->schema([
                    TextInput::make('package_price')->label('Aankoopprijs verpakking excl. btw')->numeric()->minValue(0)->prefix('€'),
                    Select::make('vat_rate')->label('Btw')->options(['0' => '0%', '6' => '6%', '12' => '12%', '21' => '21%'])->default('21')->required()
                        ->formatStateUsing(fn ($state): string => (string) (int) ($state ?? 21)),
                    Repeater::make('servings')
                        ->label('Verkocht als')
                        ->relationship()
                        ->orderColumn('sort')
                        ->columns(4)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel('Verkoopprijs toevoegen')
                        ->schema([
                            TextInput::make('label')->label('Omschrijving')->placeholder('Flesje, 25cl, Glas...')->required()->maxLength(255),
                            TextInput::make('servings_per_package')->label('Aantal per verpakking')->numeric()->minValue(0.01)->required(),
                            TextInput::make('price')->label('Verkoopprijs')->numeric()->minValue(0)->prefix('€'),
                            Toggle::make('is_primary')->label('Toogprijs')->inline(false),
                        ]),
                ]),
            Section::make('Stock en verhuur')->columns(3)->columnSpanFull()->schema([
                Toggle::make('track_stock')->label('Stock bijhouden')->default(true)->live(),
                TextInput::make('min_stock')->label('Minimumstock (stuks)')->numeric()->minValue(0)->visible(fn (Get $get): bool => (bool) $get('track_stock')),
                Toggle::make('is_orderable')->label('In het huurformulier (bestellijst)')->default(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->defaultGroup('category')
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn (Product $record): ?string => $record->package)->searchable(),
                TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->state(fn (Product $record): string => $record->track_stock ? $record->stockLabel() : '-')
                    ->color(fn (Product $record): ?string => $record->isLow() ? 'danger' : null)
                    ->weight(fn (Product $record): ?string => $record->isLow() ? 'bold' : null)
                    ->sortable(),
                TextColumn::make('min_stock')->label('Min.')->placeholder('-')->toggleable(),
                TextColumn::make('bar_price')->label('Toog')->money('EUR')->placeholder('-'),
                TextColumn::make('rental_price')->label('Huurders')->money('EUR')->placeholder('-'),
                TextColumn::make('purchase_price')->label('Aankoop')->money('EUR')->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
                ToggleColumn::make('is_orderable')->label('Bestellijst')->toggleable(),
                ToggleColumn::make('is_active')->label('Actief')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')->label('Categorie')->options(ProductCategory::class),
                Filter::make('low')->label('Onder minimumstock')->query(fn (Builder $query) => $query->lowStock()),
            ])
            ->recordActions([
                self::adjustStockAction(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function adjustStockAction(): Action
    {
        return Action::make('adjustStock')
            ->label('Stock')
            ->icon(Heroicon::OutlinedArrowsUpDown)
            ->color('gray')
            ->visible(fn (Product $record): bool => $record->track_stock && static::userCan(Permissions::STOCK))
            ->modalHeading(fn (Product $record): string => 'Stock aanpassen: '.$record->name)
            ->modalDescription(fn (Product $record): string => 'Nu in stock: '.$record->stockLabel())
            ->schema(fn (Product $record): array => [
                Select::make('type')
                    ->label('Soort')
                    ->options([
                        StockMovementType::Purchase->value => 'Aankoop / levering (+)',
                        StockMovementType::Sale->value => 'Verkocht aan de toog (-)',
                        StockMovementType::Loss->value => 'Breuk / verlies (-)',
                        StockMovementType::Correction->value => 'Correctie (+ of -)',
                    ])
                    ->default(StockMovementType::Purchase->value)
                    ->required(),
                TextInput::make('packages')->label('Aantal verpakkingen')->numeric()->minValue(0)->visible($record->units_per_package > 1)->helperText($record->units_per_package > 1 ? "× {$record->units_per_package} stuks" : null),
                TextInput::make('quantity')->label('Aantal stuks')->numeric()->integer()->helperText('Bij correctie: negatief om te verminderen.'),
                TextInput::make('note')->label('Opmerking')->maxLength(255),
            ])
            ->action(function (Product $record, array $data): void {
                $type = StockMovementType::from($data['type']);
                $quantity = (int) ($data['quantity'] ?? 0) + (int) ($data['packages'] ?? 0) * (int) $record->units_per_package;

                if ($quantity === 0) {
                    Notification::make()->warning()->title('Geen aantal ingevuld')->send();

                    return;
                }

                if (in_array($type, [StockMovementType::Sale, StockMovementType::Loss], true)) {
                    $quantity = -abs($quantity);
                } elseif ($type === StockMovementType::Purchase) {
                    $quantity = abs($quantity);
                }

                app(Stock::class)->move($record, $type, $quantity, $data['note'] ?? null);
                Notification::make()->success()->title('Stock '.$record->name.': '.$record->stockLabel())->send();
            });
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
