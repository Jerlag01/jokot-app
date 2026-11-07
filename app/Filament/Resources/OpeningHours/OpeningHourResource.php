<?php

namespace App\Filament\Resources\OpeningHours;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\OpeningHours\Pages\ManageOpeningHours;
use App\Models\OpeningHour;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * One row per weekday, seeded; only editable. The holiday note lives in Settings.
 */
class OpeningHourResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PAGES;

    protected static ?string $model = OpeningHour::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'openingsuur';

    protected static ?string $pluralModelLabel = 'openingsuren';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Toggle::make('is_closed')->label('Gesloten')->live()->columnSpanFull(),
            TextInput::make('opens_at')->label('Van')->placeholder('20u')->hidden(fn (Get $get): bool => (bool) $get('is_closed'))->maxLength(10),
            TextInput::make('closes_at')->label('Tot')->placeholder('02u')->hidden(fn (Get $get): bool => (bool) $get('is_closed'))->maxLength(10),
            TextInput::make('note')->label('Opmerking')->helperText('Ingevuld = sterretje (**) bij de dag, verwijzend naar de opmerking in Instellingen.')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('weekday')
            ->paginated(false)
            ->columns([
                TextColumn::make('weekday')->label('Dag')->formatStateUsing(fn (int $state): string => OpeningHour::DAYS[$state]),
                IconColumn::make('is_closed')->label('Gesloten')->boolean(),
                TextColumn::make('opens_at')->label('Van')->placeholder('/'),
                TextColumn::make('closes_at')->label('Tot')->placeholder('/'),
                TextColumn::make('note')->label('Opmerking')->placeholder('-'),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageOpeningHours::route('/')];
    }
}
