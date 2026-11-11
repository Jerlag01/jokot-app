<?php

namespace App\Filament\Resources\Members;

use App\Enums\NavigationGroup;
use App\Enums\PaymentMethod;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\RelationManagers\CardTransactionsRelationManager;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Models\Member;
use App\Support\MemberCards;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * The member lists: one list per year (memberships), member cards and the member's self-service link.
 */
class MemberResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::MEMBERS;

    protected static ?string $model = Member::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'lid';

    protected static ?string $pluralModelLabel = 'leden';

    protected static ?string $navigationLabel = 'Ledenlijst';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Members;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'last_name';

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'email', 'member_number', 'card_number'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->fullName();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Section::make('Gegevens')->columns(2)->schema(self::detailFields())]);
    }

    /**
     * @return list<mixed>
     */
    public static function detailFields(): array
    {
        return [
            TextInput::make('card_number')
                ->label('Kaartnummer (barcode)')
                ->placeholder('5 cijfers')
                ->helperText('Het nummer onder de barcode op de lidkaart. Scan de kaart of typ het nummer.')
                ->regex(Member::CARD_PATTERN)
                ->validationMessages(['regex' => 'Een kaartnummer bestaat uit precies 5 cijfers.', 'unique' => 'Deze kaart is al aan een ander lid gekoppeld.'])
                ->unique(ignoreRecord: true)
                ->maxLength(5)
                ->columnSpanFull(),
            TextInput::make('last_name')->label('Naam')->required()->maxLength(100),
            TextInput::make('first_name')->label('Voornaam')->required()->maxLength(100),
            TextInput::make('email')->label('E-mail')->email()->maxLength(150),
            TextInput::make('phone')->label('Telefoon')->tel()->maxLength(40),
            Grid::make(6)->columnSpanFull()->schema([
                TextInput::make('street')->label('Straat')->columnSpan(3),
                TextInput::make('house_number')->label('Nr.'),
                TextInput::make('box')->label('Bus'),
                TextInput::make('postal_code')->label('Postcode'),
                TextInput::make('city')->label('Gemeente')->columnSpan(3),
            ]),
            Textarea::make('notes')->label('Notities (intern)')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('memberships')->withSum(['cardTransactions as card_balance' => fn (Builder $query) => $query->completed()->reorder()], 'amount'))
            ->columns([
                TextColumn::make('member_number')->label('Nr.')->searchable()->sortable(),
                TextColumn::make('card_number')->label('Kaart')->searchable()->sortable()->placeholder('-')->fontFamily('mono'),
                TextColumn::make('card_balance')->label('Saldo')->money('EUR', locale: 'nl_BE')->sortable()->placeholder('-')->toggleable(),
                TextColumn::make('last_name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('first_name')->label('Voornaam')->searchable()->sortable(),
                TextColumn::make('address')->label('Adres')->state(fn (Member $record): ?string => $record->address())->placeholder('-')->toggleable(),
                TextColumn::make('email')->label('E-mail')->searchable()->placeholder('-')->toggleable(),
                TextColumn::make('phone')->label('Telefoon')->placeholder('-')->toggleable(),
                TextColumn::make('years')->label('Lid in')->state(fn (Member $record): string => $record->memberships->pluck('year')->sortDesc()->take(4)->implode(', '))->badge()->color('gray'),
                TextColumn::make('details_updated_at')->label('Zelf aangepast')->since()->placeholder('-')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('year')
                    ->label('Ledenlijst')
                    ->options(fn (): array => collect(range((int) now()->year + 1, (int) now()->year - 6))->mapWithKeys(fn (int $year): array => [$year => "Leden {$year}"])->all())
                    ->default((string) now()->year)
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null, fn (Builder $query, string $year) => $query->memberIn((int) $year))),
            ])
            ->recordActions([
                Action::make('card')->label('Lidkaart')->icon(Heroicon::OutlinedIdentification)->color('gray')
                    ->schema(self::cardOptions())
                    ->action(fn (Member $record, array $data) => self::downloadCards(collect([$record]), (int) $data['year'], $data['layout'])),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('cards')->label('Lidkaarten afdrukken')->icon(Heroicon::OutlinedIdentification)
                        ->schema(self::cardOptions())
                        ->action(fn (Collection $records, array $data) => self::downloadCards($records, (int) $data['year'], $data['layout'])),
                    BulkAction::make('renew')->label('Lid maken voor een jaar')->icon(Heroicon::OutlinedArrowPath)
                        ->schema(self::membershipFields())
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn (Member $member) => $member->memberships()->firstOrCreate(['year' => $data['year']], collect($data)->except('year')->all()));
                            Notification::make()->success()->title($records->count().' leden ingeschreven voor '.$data['year'])->send();
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return list<mixed>
     */
    public static function membershipFields(): array
    {
        return [
            TextInput::make('year')->label('Jaar')->numeric()->default(now()->year)->required(),
            TextInput::make('amount')->label('Lidgeld')->numeric()->prefix('€')->default(fn () => settings()->get('member_fee'))->required(),
            Select::make('method')->label('Betaald met')->options(PaymentMethod::class)->default(PaymentMethod::Cash),
            DatePicker::make('paid_on')->label('Betaald op')->default(today())->native(false)->displayFormat('d/m/Y'),
        ];
    }

    /**
     * @return list<mixed>
     */
    private static function cardOptions(): array
    {
        return [
            TextInput::make('year')->label('Jaar op de kaart')->numeric()->default(now()->year)->required(),
            Radio::make('layout')->label('Formaat')->options([
                'a4' => 'A4 met 10 kaarten om uit te knippen',
                'card' => 'Eén kaart per pagina (kaartprinter, 85,6 × 54 mm)',
            ])->default('a4')->required(),
        ];
    }

    /**
     * @param  Collection<int, Member>  $members
     */
    public static function downloadCards(Collection $members, int $year, string $layout): StreamedResponse
    {
        $pdf = app(MemberCards::class)->pdf($members->sortBy('last_name')->values(), $year, $layout)->output();

        foreach ($members as $member) {
            $member->memberships()->where('year', $year)->update(['card_printed_at' => now()]);
        }

        return response()->streamDownload(fn () => print ($pdf), "lidkaarten-{$year}.pdf", ['Content-Type' => 'application/pdf']);
    }

    public static function getRelations(): array
    {
        return [MembershipsRelationManager::class, CardTransactionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'edit' => EditMember::route('/{record}/edit'),
        ];
    }
}
