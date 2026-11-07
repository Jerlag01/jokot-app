<?php

namespace App\Filament\Resources\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\Page;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Website pages, built from blocks. Each block type has a view in resources/views/blocks.
 */
class PageResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::PAGES;

    protected static ?string $model = Page::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'pagina';

    protected static ?string $pluralModelLabel = 'pagina\'s';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Website;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()
                ->columnSpan(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Titel')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, Get $get, Set $set, string $operation): void {
                            if ($operation === 'create' && blank($get('slug'))) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    Builder::make('blocks')
                        ->label('Inhoud')
                        ->blocks(self::blocks())
                        ->collapsible()
                        ->cloneable()
                        ->blockNumbers(false)
                        ->addActionLabel('Blok toevoegen'),
                ]),
            Section::make()
                ->columnSpan(1)
                ->schema([
                    TextInput::make('slug')
                        ->label('URL')
                        ->prefix('/')
                        ->required()
                        ->alphaDash()
                        ->unique(ignoreRecord: true)
                        ->notIn(['admin', 'livewire', 'storage', 'filament', 'api', 'agenda', 'huuraanvraag', 'kalender', 'bestelling', 'contract', 'webhooks'])
                        ->disabled(fn (?Page $record): bool => (bool) $record?->isHome())
                        ->dehydrated(fn (?Page $record): bool => ! $record?->isHome())
                        ->helperText(fn (?Page $record): ?string => $record?->isHome() ? 'Dit is de startpagina.' : null),
                    Toggle::make('is_published')->label('Gepubliceerd')->default(true),
                    Toggle::make('show_in_menu')->label('Tonen in het menu'),
                    TextInput::make('menu_order')->label('Volgorde in het menu')->numeric()->default(0),
                    Textarea::make('meta_description')->label('Beschrijving voor Google')->rows(3)->maxLength(255),
                ]),
        ]);
    }

    /**
     * @return list<Block>
     */
    public static function blocks(): array
    {
        $button = [
            Grid::make(2)->schema([
                TextInput::make('button_label')->label('Knoptekst'),
                TextInput::make('button_url')->label('Knoplink')->placeholder('/verhuren'),
            ]),
        ];

        return [
            Block::make('hero')
                ->label('Banner met foto')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([
                    TextInput::make('eyebrow')->label('Kleine tekst boven de titel')->placeholder('Welkom bij'),
                    TextInput::make('heading')->label('Titel')->required(),
                    Textarea::make('text')->label('Tekst')->rows(2),
                    self::imageUpload(),
                    Select::make('size')->label('Hoogte')->options(['large' => 'Groot', 'small' => 'Klein'])->default('large'),
                    ...$button,
                ]),
            Block::make('cta')
                ->label('Foto + oproep')
                ->icon(Heroicon::OutlinedMegaphone)
                ->schema([
                    TextInput::make('heading')->label('Titel')->required(),
                    Textarea::make('text')->label('Tekst')->rows(2),
                    self::imageUpload(),
                    Toggle::make('image_right')->label('Foto rechts'),
                    ...$button,
                ]),
            Block::make('text')
                ->label('Tekst')
                ->icon(Heroicon::OutlinedBars3BottomLeft)
                ->schema([
                    TextInput::make('heading')->label('Titel'),
                    RichEditor::make('content')
                        ->label('Tekst')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('pages')
                        ->fileAttachmentsVisibility('public'),
                    TextInput::make('anchor')->label('Anker (voor links naar #...)')->alphaDash(),
                ]),
            Block::make('image')
                ->label('Afbeelding')
                ->icon(Heroicon::OutlinedPhoto)
                ->schema([self::imageUpload()->required(), TextInput::make('caption')->label('Onderschrift')]),
            Block::make('opening_hours')
                ->label('Openingsuren')
                ->icon(Heroicon::OutlinedClock)
                ->schema([TextInput::make('heading')->label('Titel')->default('Onze openingsuren')]),
            Block::make('rental_rates')
                ->label('Tarieven verhuur')
                ->icon(Heroicon::OutlinedCurrencyEuro)
                ->schema([TextInput::make('heading')->label('Titel')->default('Tarieven')]),
            Block::make('rental_extras')
                ->label('Materialen & diensten')
                ->icon(Heroicon::OutlinedMusicalNote)
                ->schema([TextInput::make('heading')->label('Titel'), Textarea::make('intro')->label('Inleiding')->rows(2)]),
            Block::make('drink_prices')
                ->label('Drankprijzen')
                ->icon(Heroicon::OutlinedBeaker)
                ->schema([TextInput::make('heading')->label('Titel')->default('Drankprijzen')]),
            Block::make('events')
                ->label('Agenda (komende activiteiten)')
                ->icon(Heroicon::OutlinedSparkles)
                ->schema([
                    TextInput::make('heading')->label('Titel')->default('Agenda'),
                    TextInput::make('limit')->label('Maximum aantal')->numeric()->default(6),
                    TextInput::make('empty_text')->label('Tekst als er niets gepland is'),
                ]),
            Block::make('calendar')
                ->label('Beschikbaarheidskalender')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([TextInput::make('heading')->label('Titel')->default('Kalender'), Textarea::make('intro')->label('Inleiding')->rows(2)]),
            Block::make('cup_loan_form')
                ->label('Aanvraagformulier wisselbekers')
                ->icon(Heroicon::OutlinedBeaker)
                ->schema([TextInput::make('heading')->label('Titel')->default('Wisselbekers aanvragen')]),
            Block::make('rental_form')
                ->label('Huuraanvraagformulier')
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->schema([TextInput::make('heading')->label('Titel')->default('Huuraanvraag'), Textarea::make('intro')->label('Inleiding')->rows(2)]),
        ];
    }

    private static function imageUpload(): FileUpload
    {
        return FileUpload::make('image')
            ->label('Foto')
            ->image()
            ->disk('public')
            ->directory('pages')
            ->visibility('public')
            ->imageEditor()
            ->helperText('Leeg = standaardfoto van de zaal.');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('menu_order')
            ->columns([
                TextColumn::make('title')->label('Titel')->searchable()->sortable(),
                TextColumn::make('slug')->label('URL')->formatStateUsing(fn (string $state): string => $state === Page::HOME_SLUG ? '/' : '/'.$state)->color('gray'),
                IconColumn::make('is_published')->label('Online')->boolean(),
                IconColumn::make('show_in_menu')->label('Menu')->boolean(),
                TextColumn::make('menu_order')->label('Volgorde')->sortable(),
                TextColumn::make('updated_at')->label('Gewijzigd')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')->label('Bekijk')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Page $record): string => self::publicUrl($record), shouldOpenInNewTab: true),
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Page $record): bool => $record->isHome()),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && ! $record->isHome();
    }

    public static function publicUrl(Page $record): string
    {
        return $record->url();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'create' => CreatePage::route('/create'),
            'edit' => EditPage::route('/{record}/edit'),
        ];
    }
}
