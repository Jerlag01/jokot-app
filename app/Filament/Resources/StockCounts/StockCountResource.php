<?php

namespace App\Filament\Resources\StockCounts;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StockCounts\Pages\EditStockCount;
use App\Filament\Resources\StockCounts\Pages\ListStockCounts;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Stock counts (inventaris): count everything, then finalise to book the differences.
 */
class StockCountResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::STOCK;

    protected static ?string $model = StockCount::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'telling';

    protected static ?string $pluralModelLabel = 'tellingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 2;

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && ! $record->isFinalized();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                DatePicker::make('counted_on')->label('Datum')->required()->native(false)->displayFormat('d/m/Y'),
                TextInput::make('note')->label('Opmerking')->maxLength(255),
            ]),
            Section::make('Telling')
                ->description('Vul per product het getelde aantal stuks in. Lege lijnen worden overgeslagen bij het afsluiten.')
                ->schema([
                    Repeater::make('lines')
                        ->hiddenLabel()
                        ->relationship('lines', fn (Builder $query) => $query->join('products', 'products.id', '=', 'stock_count_lines.product_id')
                            ->orderBy('products.category')->orderBy('products.sort')->select('stock_count_lines.*'))
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->schema([
                            Grid::make(4)->schema([
                                Text::make(fn (?StockCountLine $record): string => $record?->product?->label() ?? '')->columnSpan(2),
                                Text::make(fn (?StockCountLine $record): string => 'Verwacht: '.($record ? $record->product->stockLabel($record->expected) : '')),
                                TextInput::make('counted')
                                    ->hiddenLabel()
                                    ->placeholder('Geteld')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->hint(fn (Get $get, ?StockCountLine $record): ?string => filled($get('counted')) && $record
                                        ? (($difference = (int) $get('counted') - $record->expected) === 0 ? 'klopt' : (($difference > 0 ? '+' : '').$difference))
                                        : null)
                                    ->hintColor(fn (Get $get, ?StockCountLine $record): string => filled($get('counted')) && $record && (int) $get('counted') !== $record->expected ? 'danger' : 'success'),
                            ]),
                        ]),
                ]),
        ])->disabled(fn (?StockCount $record): bool => (bool) $record?->isFinalized());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('counted_on', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount(['lines', 'lines as counted_count' => fn (Builder $query) => $query->whereNotNull('counted')]))
            ->columns([
                TextColumn::make('counted_on')->label('Datum')->date('d/m/Y')->sortable(),
                TextColumn::make('note')->label('Opmerking')->placeholder('-'),
                TextColumn::make('progress')->label('Geteld')->state(fn (StockCount $record): string => $record->counted_count.' / '.$record->lines_count),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (StockCount $record): string => $record->isFinalized() ? 'Afgesloten' : 'Bezig')
                    ->color(fn (string $state): string => $state === 'Afgesloten' ? 'success' : 'warning'),
                TextColumn::make('user.name')->label('Door')->placeholder('-'),
            ])
            ->recordActions([EditAction::make()->label(fn (StockCount $record): string => $record->isFinalized() ? 'Bekijken' : 'Tellen'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockCounts::route('/'),
            'edit' => EditStockCount::route('/{record}/edit'),
        ];
    }
}
