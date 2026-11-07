<?php

namespace App\Filament\Resources\StockMovements;

use App\Enums\NavigationGroup;
use App\Enums\StockMovementType;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\StockMovement;
use App\Support\Permissions;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * The stock ledger: read-only, every change is booked through App\Support\Stock.
 */
class StockMovementResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::STOCK;

    protected static ?string $model = StockMovement::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'stockbeweging';

    protected static ?string $pluralModelLabel = 'stockbewegingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Stock;

    protected static ?int $navigationSort = 3;

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

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Wanneer')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('quantity')->label('Aantal')->numeric()->color(fn (int $state): string => $state < 0 ? 'danger' : 'success')->formatStateUsing(fn (int $state): string => ($state > 0 ? '+' : '').$state),
                TextColumn::make('balance_after')->label('Stock na')->numeric(),
                TextColumn::make('note')->label('Opmerking')->placeholder('-')->wrap(),
                TextColumn::make('user.name')->label('Door')->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('product')->label('Product')->relationship('product', 'name')->searchable()->preload(),
                SelectFilter::make('type')->label('Soort')->options(StockMovementType::class)->multiple(),
                Filter::make('period')
                    ->schema([DatePicker::make('from')->label('Van'), DatePicker::make('until')->label('Tot')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListStockMovements::route('/')];
    }
}
