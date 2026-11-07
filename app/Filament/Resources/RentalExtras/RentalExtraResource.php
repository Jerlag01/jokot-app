<?php

namespace App\Filament\Resources\RentalExtras;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\RentalExtras\Pages\ManageRentalExtras;
use App\Models\RentalExtra;
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

class RentalExtraResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::RENTAL_SETTINGS;

    protected static ?string $model = RentalExtra::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'materiaal of dienst';

    protected static ?string $pluralModelLabel = 'materialen & diensten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('description')->label('Omschrijving')->maxLength(255)->columnSpanFull(),
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
                TextColumn::make('name')->label('Naam')->searchable()->description(fn (RentalExtra $record): ?string => $record->description),
                TextColumn::make('price')->label('Huurprijs')->money('EUR'),
                TextColumn::make('deposit')->label('Waarborg')->money('EUR'),
                ToggleColumn::make('is_active')->label('Actief'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalExtras::route('/')];
    }
}
