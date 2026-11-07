<?php

namespace App\Filament\Resources\ContractTemplates;

use App\Enums\ContractType;
use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\ContractTemplates\Pages\EditContractTemplate;
use App\Filament\Resources\ContractTemplates\Pages\ListContractTemplates;
use App\Models\ContractTemplate;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * The texts of the two contracts. {placeholders} are filled in per rental or cup loan.
 */
class ContractTemplateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::RENTAL_SETTINGS;

    protected static ?string $model = ContractTemplate::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'contractsjabloon';

    protected static ?string $pluralModelLabel = 'contractsjablonen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Rentals;

    protected static ?int $navigationSort = 6;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function placeholders(ContractType $type): array
    {
        $common = ['referentie' => 'referentie (HA-... / WB-...)', 'verhuurder' => 'JOC Niel vzw', 'vertegenwoordiger' => 'vertegenwoordiger jeugdhuis (Instellingen)', 'huurder' => 'huurder / organisatie', 'huurder_adres' => 'adres huurder', 'iban' => 'rekeningnummer', 'vandaag' => 'datum van vandaag'];

        return $type === ContractType::Rental
            ? [...$common, 'datum' => 'datum van het event', 'omschrijving' => 'details van de aanvraag', 'huurder_naam' => 'naam huurder', 'huurder_contact' => 'e-mail en telefoon', 'gebruikersgroep' => 'gebruikersgroep', 'huurprijs' => 'huurprijs', 'waarborg' => 'waarborg', 'dj' => 'DJ-installatie', 'prijzen' => 'prijstabel (tarieven, materialen, drank)', 'contact' => 'contactpersonen (Instellingen)']
            : [...$common, 'huurder_vertegenwoordiger' => 'vertegenwoordiger huurder', 'aantal_bekers' => 'aantal bekers', 'aantal_bakken' => 'aantal bakken', 'van' => 'ophaaldatum', 'tot' => 'terugbrengdatum', 'evenement' => 'evenement', 'huurprijs' => 'huurprijs', 'prijs_beker' => 'prijs per beker', 'prijs_bak' => 'prijs per bak'];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Titel')->required()->maxLength(255),
            Text::make(fn (ContractTemplate $record): HtmlString => new HtmlString('<strong>Invulvelden</strong> (worden per contract ingevuld): '.collect(self::placeholders($record->type))->map(fn (string $label, string $key): string => '<code>{'.$key.'}</code> '.e($label))->implode(', ').'. Ondertekende contracten veranderen niet meer als je de tekst aanpast.')),
            RichEditor::make('body')->label('Tekst')->required()->toolbarButtons([['bold', 'italic', 'underline', 'link'], ['h2', 'h3'], ['bulletList', 'orderedList'], ['table'], ['undo', 'redo']]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->label('Soort')->badge(),
                TextColumn::make('title')->label('Titel'),
                TextColumn::make('updated_at')->label('Gewijzigd')->since(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContractTemplates::route('/'),
            'edit' => EditContractTemplate::route('/{record}/edit'),
        ];
    }
}
