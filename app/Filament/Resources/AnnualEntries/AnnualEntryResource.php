<?php

namespace App\Filament\Resources\AnnualEntries;

use App\Enums\AnnualLine;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\AnnualEntries\Pages\ManageAnnualEntries;
use App\Models\AnnualEntry;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Amounts for the yearly accounts that are not in the app: subsidy, volunteer fees, cash expenses, old figures.
 */
class AnnualEntryResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::ACCOUNTS;

    protected static ?string $model = AnnualEntry::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'manuele boeking';

    protected static ?string $pluralModelLabel = 'manuele boekingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('year')->label('Boekjaar')->numeric()->minValue(2000)->maxValue(2100)->default(now()->year)->required(),
            DatePicker::make('date')->label('Datum')->native(false)->displayFormat('d/m/Y'),
            Select::make('line')->label('Lijn in de jaarrekening')->options(AnnualLine::allOptions())->searchable()->required()->columnSpanFull(),
            TextInput::make('description')->label('Omschrijving')->required()->maxLength(255)->columnSpanFull(),
            TextInput::make('amount')->label('Bedrag')->numeric()->prefix('€')->required()->helperText('Positief. Negatief enkel om een bedrag te corrigeren.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('year', 'desc')
            ->columns([
                TextColumn::make('year')->label('Jaar')->sortable(),
                TextColumn::make('date')->label('Datum')->date('d/m/Y')->placeholder('-'),
                TextColumn::make('line')->label('Lijn')->formatStateUsing(fn (AnnualLine $state): string => $state->optionLabel())->badge()->color(fn (AnnualLine $state): string => $state->isIncome() ? 'success' : 'warning'),
                TextColumn::make('description')->label('Omschrijving')->searchable()->wrap(),
                TextColumn::make('amount')->label('Bedrag')->money('EUR')->summarize(Sum::make()->money('EUR')->label('')),
                IconColumn::make('is_import')->label('Uit Excel')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('year')->label('Jaar')->options(fn (): array => AnnualEntry::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('line')->label('Lijn')->options(AnnualLine::allOptions()),
                TernaryFilter::make('is_import')->label('Overgenomen uit Excel'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAnnualEntries::route('/')];
    }
}
