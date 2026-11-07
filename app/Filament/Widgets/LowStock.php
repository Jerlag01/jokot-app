<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Permissions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LowStock extends TableWidget
{
    protected static ?int $sort = 5;

    protected static ?string $heading = 'Bijbestellen (onder minimumstock)';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasPermission(Permissions::PRODUCTS) && Product::query()->lowStock()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Product::query()->lowStock()->orderBy('category')->orderBy('sort'))
            ->paginated(false)
            ->recordUrl(fn (): string => ProductResource::getUrl('index', ['tableFilters' => ['low' => ['isActive' => true]]]))
            ->columns([
                TextColumn::make('name')->label('Product')->description(fn (Product $record): ?string => $record->package),
                TextColumn::make('stock')->label('Stock')->state(fn (Product $record): string => $record->stockLabel())->color('danger'),
                TextColumn::make('min_stock')->label('Minimum'),
            ]);
    }
}
