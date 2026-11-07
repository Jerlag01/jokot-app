<?php

namespace App\Filament\Resources\ExpenseCategories;

use App\Enums\AnnualLine;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ExpenseCategories\Pages\ManageExpenseCategories;
use App\Models\ExpenseCategory;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ExpenseCategoryResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PURCHASES;

    protected static ?string $model = ExpenseCategory::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'kostencategorie';

    protected static ?string $pluralModelLabel = 'kostencategorieën';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(100)->unique(ignoreRecord: true),
            Select::make('annual_line')->label('Lijn in de jaarrekening')->options(AnnualLine::expenseOptions())->searchable()->helperText('Leeg = "Overige uitgaven".'),
            Toggle::make('is_active')->label('Actief')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->modifyQueryUsing(fn (Builder $query) => $query->withSum('purchaseInvoices', 'total')->withCount('purchaseInvoices'))
            ->columns([
                TextColumn::make('name')->label('Naam'),
                TextColumn::make('annual_line')->label('Jaarrekening')->formatStateUsing(fn (?AnnualLine $state): string => $state?->getLabel() ?? '-')->badge()->color('gray'),
                TextColumn::make('purchase_invoices_count')->label('Facturen'),
                TextColumn::make('purchase_invoices_sum_total')->label('Totaal (alles)')->money('EUR')->placeholder('€0'),
                ToggleColumn::make('is_active')->label('Actief'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && $record->purchaseInvoices()->doesntExist();
    }

    public static function getPages(): array
    {
        return ['index' => ManageExpenseCategories::route('/')];
    }
}
