<?php

namespace App\Filament\Resources\RentalBlockedDates;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\RentalBlockedDates\Pages\ManageRentalBlockedDates;
use App\Models\RentalBlockedDate;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Days and periods without rental requests via the website. Weekdays without requests are set in the header action.
 */
class RentalBlockedDateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::RENTAL_SETTINGS;

    protected static ?string $model = RentalBlockedDate::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'geblokkeerde datum';

    protected static ?string $pluralModelLabel = 'geblokkeerde data';

    protected static ?string $navigationLabel = 'Geblokkeerde data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            DatePicker::make('starts_on')->label('Van')->required()->native(false)->displayFormat('d/m/Y')->live(),
            DatePicker::make('ends_on')->label('Tot en met')->native(false)->displayFormat('d/m/Y')
                ->minDate(fn (Get $get) => $get('starts_on'))
                ->helperText('Leeg = enkel die ene dag.'),
            TextInput::make('reason')->label('Reden (enkel intern)')->placeholder('bv. werken, kamp, eigen fuif')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('starts_on')->label('Van')->date('D d/m/Y')->sortable(),
                TextColumn::make('ends_on')->label('Tot en met')->date('D d/m/Y'),
                TextColumn::make('days')->label('Dagen')->state(fn (RentalBlockedDate $record): int => $record->days()),
                TextColumn::make('reason')->label('Reden')->placeholder('-')->wrap(),
            ])
            ->filters([
                TernaryFilter::make('upcoming')->label('Periode')->trueLabel('Huidig en toekomstig')->falseLabel('Voorbij')->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('ends_on', '>=', today()),
                        false: fn (Builder $query) => $query->where('ends_on', '<', today()),
                    ),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])])
            ->emptyStateHeading('Geen geblokkeerde data')
            ->emptyStateDescription('Voeg een dag of periode toe waarop de zaal niet aangevraagd kan worden.');
    }

    public static function getPages(): array
    {
        return ['index' => ManageRentalBlockedDates::route('/')];
    }
}
