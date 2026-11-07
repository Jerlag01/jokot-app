<?php

namespace App\Filament\Resources\RentalRates;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\RentalRates\Pages\ManageRentalRates;
use App\Models\RentalRate;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class RentalRateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::RENTAL_SETTINGS;

    protected static ?string $model = RentalRate::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'tarief';

    protected static ?string $pluralModelLabel = 'tarieven';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(255)->columnSpanFull(),

            TextInput::make('price')->label('Huurprijs')->numeric()->prefix('€')->required(),
            TextInput::make('deposit')->label('Waarborg')->numeric()->prefix('€')->default(0)->required(),
            Toggle::make('is_active')->label('Actief (zichtbaar op de website)')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable(),
                TextColumn::make('price')->label('Huurprijs')->money('EUR'),
                TextColumn::make('deposit')->label('Waarborg')->money('EUR'),
                ToggleColumn::make('is_active')->label('Actief'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalRates::route('/')];
    }
}
