<?php

namespace App\Filament\Resources\Customers;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Models\Customer;
use App\Support\Billit\Billit;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CustomerResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::INVOICES;

    protected static ?string $model = Customer::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'klant';

    protected static ?string $pluralModelLabel = 'klanten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::fields());
    }

    /**
     * Also used to create a customer from within the invoice form.
     *
     * @return list<mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label('Naam / organisatie')->required()->maxLength(255),
            TextInput::make('contact_name')->label('Contactpersoon')->maxLength(255),
            TextInput::make('vat_number')->label('Btw- of ondernemingsnummer')->maxLength(30),
            TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            TextInput::make('phone')->label('Telefoon')->tel()->maxLength(40),
            Grid::make(6)->columnSpanFull()->schema([
                TextInput::make('street')->label('Straat')->columnSpan(3),
                TextInput::make('house_number')->label('Nr.'),
                TextInput::make('box')->label('Bus'),
                TextInput::make('country')->label('Land')->default('BE')->maxLength(2),
                TextInput::make('postal_code')->label('Postcode')->columnSpan(2),
                TextInput::make('city')->label('Gemeente')->columnSpan(4),
            ]),
            Textarea::make('notes')->label('Notities')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('invoices'))
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn (Customer $record): ?string => $record->contact_name)->searchable(['name', 'contact_name'])->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('city')->label('Gemeente')->toggleable(),
                TextColumn::make('vat_number')->label('Btw-nr.')->placeholder('-')->toggleable(),
                TextColumn::make('peppol_provider')->label('Peppol')->placeholder('-')->toggleable()
                    ->state(fn (Customer $record): ?string => $record->peppol_checked_at ? ($record->peppol_identifier ? ($record->peppol_provider ?: 'ja') : 'nee') : null),
                TextColumn::make('invoices_count')->label('Facturen')->numeric(),
            ])
            ->recordActions([
                Action::make('peppol')->label('Peppol')->icon(Heroicon::OutlinedSignal)->color('gray')
                    ->visible(fn (Customer $record): bool => filled($record->vat_number))
                    ->action(function (Customer $record): void {
                        try {
                            $result = app(Billit::class)->checkPeppol($record);
                            Notification::make()->{$result['registered'] ? 'success' : 'warning'}()
                                ->title($result['registered'] ? 'Op Peppol: '.$result['identifier'] : 'Niet op Peppol')
                                ->body($result['provider'] ? 'Ontvangt via '.$result['provider'] : ($result['registered'] ? null : 'Facturen gaan per e-mail.'))->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->danger()->title('Opzoeken mislukt')->body($exception->getMessage())->send();
                        }
                    }),
                Action::make('invoice')->label('Factuur')->icon(Heroicon::OutlinedDocumentPlus)->color('gray')
                    ->url(fn (Customer $record): string => InvoiceResource::getUrl('create', ['customer' => $record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && $record->invoices()->doesntExist();
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
