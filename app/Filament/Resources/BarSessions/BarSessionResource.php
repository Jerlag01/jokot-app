<?php

namespace App\Filament\Resources\BarSessions;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\BarSessions\Pages\ManageBarSessions;
use App\Models\BarSession;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Bar takings ("Toog Inkomsten"): per evening, or per month for SumUp-only totals.
 */
class BarSessionResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::ACCOUNTS;

    protected static ?string $model = BarSession::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'kassa-afsluiting';

    protected static ?string $pluralModelLabel = 'toogkassa';

    protected static ?string $navigationLabel = 'Toogkassa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            DatePicker::make('date')->label('Datum')->default(today())->required()->native(false)->displayFormat('d/m/Y'),
            TextInput::make('label')->label('Omschrijving')->placeholder('bv. caféavond, SumUp januari')->columnSpan(2),
            TextInput::make('opening_cash')->label('Beginkassa')->numeric()->prefix('€')->live(onBlur: true),
            TextInput::make('closing_cash')->label('Eindkassa')->numeric()->prefix('€')->live(onBlur: true),
            TextInput::make('card_total')->label('SumUp / kaart')->numeric()->prefix('€')->default(0)->live(onBlur: true),
            Text::make(fn (Get $get): string => 'Omzet: '.euro((float) $get('closing_cash') - (float) $get('opening_cash') + (float) $get('card_total')))->columnSpanFull(),
            TextInput::make('member_cards_sold')->label('Aantal lidkaarten verkocht')->numeric()->minValue(0)->default(0)->helperText('Ter info; de lidgelden zelf komen uit de ledenlijst.'),
            Textarea::make('notes')->label('Opmerking')->rows(2)->columnSpan(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->label('Datum')->date('D d/m/Y')->sortable(),
                TextColumn::make('label')->label('Omschrijving')->placeholder('-'),
                TextColumn::make('opening_cash')->label('Begin')->money('EUR')->placeholder('-'),
                TextColumn::make('closing_cash')->label('Eind')->money('EUR')->placeholder('-'),
                TextColumn::make('card_total')->label('SumUp')->money('EUR')->summarize(Sum::make()->money('EUR')->label('')),
                TextColumn::make('revenue')->label('Omzet')->money('EUR')->weight('bold')->summarize(Sum::make()->money('EUR')->label('')),
                TextColumn::make('member_cards_sold')->label('Lidkaarten')->summarize(Sum::make()->label('')),
            ])
            ->filters([
                SelectFilter::make('year')->label('Jaar')
                    ->options(fn (): array => collect(range((int) now()->year, (int) now()->year - 5))->mapWithKeys(fn (int $year): array => [$year => (string) $year])->all())
                    ->default((string) now()->year)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $year) => $query->whereYear('date', $year))),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBarSessions::route('/')];
    }
}
