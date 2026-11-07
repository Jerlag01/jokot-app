<?php

namespace App\Filament\Resources\Suppliers;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Supplier;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupplierResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PURCHASES;

    protected static ?string $model = Supplier::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'leverancier';

    protected static ?string $pluralModelLabel = 'leveranciers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components(self::fields());
    }

    /**
     * Also used to add a supplier from within the purchase invoice form.
     *
     * @return list<mixed>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')->label('Naam')->required()->maxLength(255),
            TextInput::make('vat_number')->label('Btw-nummer')->maxLength(30),
            TextInput::make('iban')->label('IBAN')->maxLength(40),
            TextInput::make('bic')->label('BIC')->maxLength(15),
            TextInput::make('email')->label('E-mail')->email(),
            TextInput::make('phone')->label('Telefoon')->tel(),
            Select::make('default_category_id')->label('Standaard kostencategorie')->relationship('defaultCategory', 'name', fn (Builder $query) => $query->active()),
            TextInput::make('payment_days')->label('Betalingstermijn (dagen)')->numeric()->minValue(0)->placeholder('30'),
            Grid::make(6)->columnSpanFull()->schema([
                TextInput::make('street')->label('Straat')->columnSpan(3),
                TextInput::make('house_number')->label('Nr.'),
                TextInput::make('postal_code')->label('Postcode'),
                TextInput::make('city')->label('Gemeente'),
            ]),
            Textarea::make('notes')->label('Notities')->rows(2)->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('purchaseInvoices')->withSum('purchaseInvoices', 'total'))
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('defaultCategory.name')->label('Categorie')->placeholder('-'),
                TextColumn::make('iban')->label('IBAN')->placeholder('-')->toggleable(),
                TextColumn::make('purchase_invoices_count')->label('Facturen'),
                TextColumn::make('purchase_invoices_sum_total')->label('Totaal')->money('EUR')->placeholder('€0'),
            ])
            ->recordActions([
                Action::make('invoices')->label('Facturen')->icon(Heroicon::OutlinedDocumentText)->color('gray')
                    ->url(fn (Supplier $record): string => PurchaseInvoiceResource::getUrl('index', ['filters' => ['supplier' => ['value' => $record->id]]])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && $record->purchaseInvoices()->doesntExist();
    }

    public static function getPages(): array
    {
        return ['index' => ManageSuppliers::route('/')];
    }
}
