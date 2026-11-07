<?php

namespace App\Filament\Resources\Events;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * The youth centre's own activities: shown in the agenda, and they block the date for rentals.
 */
class EventResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::EVENTS;

    protected static ?string $model = Event::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'activiteit';

    protected static ?string $pluralModelLabel = 'activiteiten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Tickets;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(3)->components([
            Section::make()
                ->columnSpan(2)
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('title')->label('Titel')->required()->maxLength(255)->columnSpan(2),
                        Select::make('kind')->label('Soort')->options(['event' => 'Evenement', 'activity' => 'Activiteit'])->default('event')->required()
                            ->helperText('Bepaalt de lijn in de jaarrekening.'),
                    ]),
                    Grid::make(2)->schema([
                        DateTimePicker::make('starts_at')->label('Begin')->required()->seconds(false)->native(false)->displayFormat('d/m/Y H:i'),
                        DateTimePicker::make('ends_at')->label('Einde')->seconds(false)->native(false)->displayFormat('d/m/Y H:i')->after('starts_at'),
                    ]),
                    Textarea::make('summary')->label('Korte samenvatting')->rows(2)->maxLength(500),
                    RichEditor::make('description')
                        ->label('Beschrijving')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('events')
                        ->fileAttachmentsVisibility('public'),
                ]),
            Section::make()
                ->columnSpan(1)
                ->schema([
                    FileUpload::make('image')->label('Afbeelding')->image()->disk('public')->directory('events')->visibility('public')->imageEditor(),
                    TextInput::make('price_label')->label('Prijs / inkom')->placeholder('bv. €5 - leden €3,50'),
                    TextInput::make('ticket_url')->label('Externe ticketlink')->url()->helperText('Enkel als je tickets elders verkoopt.'),
                    Toggle::make('is_published')->label('Gepubliceerd')->default(true),
                    Toggle::make('blocks_rentals')->label('Blokkeert de datum voor verhuur')->default(true)->helperText('Voorrang aan de eigen werking.'),
                    Toggle::make('tickets_enabled')->label('Online ticketverkoop')->live()->helperText('Maak tickettypes aan onderaan de pagina.'),
                    TextInput::make('capacity')->label('Max. aantal tickets (totaal)')->numeric()->minValue(1)->visible(fn (Get $get): bool => (bool) $get('tickets_enabled')),
                    Textarea::make('ticket_info')->label('Info bij de tickets')->rows(2)->visible(fn (Get $get): bool => (bool) $get('tickets_enabled')),
                    TextInput::make('slug')->label('URL')->prefix('/agenda/')->helperText('Leeg = automatisch.')->unique(ignoreRecord: true)->alphaDash(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at')
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->square()->imageSize(44),
                TextColumn::make('title')->label('Titel')->searchable()->sortable(),
                TextColumn::make('starts_at')->label('Datum')->dateTime('D d/m/Y H:i')->sortable(),
                TextColumn::make('price_label')->label('Inkom')->placeholder('-'),
                IconColumn::make('is_published')->label('Online')->boolean(),
                IconColumn::make('blocks_rentals')->label('Blokkeert verhuur')->boolean()->toggleable(),
                TextColumn::make('tickets_count')->label('Tickets')->counts(['tickets' => fn ($query) => $query->whereNull('cancelled_at')])
                    ->formatStateUsing(fn (int $state, Event $record): string => $record->tickets_enabled ? $state.($record->capacity ? ' / '.$record->capacity : '') : '-'),
            ])
            ->filters([
                TernaryFilter::make('upcoming')
                    ->label('Periode')
                    ->placeholder('Alles')
                    ->trueLabel('Komende')
                    ->falseLabel('Voorbije')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->where('starts_at', '>=', today()),
                        false: fn (Builder $query) => $query->where('starts_at', '<', today()),
                    ),
            ])
            ->recordActions([
                Action::make('open')->label('Bekijk')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (Event $record): string => route('events.show', $record), shouldOpenInNewTab: true)
                    ->visible(fn (Event $record): bool => $record->is_published),
                ReplicateAction::make()->label('Dupliceren')
                    ->excludeAttributes(['slug'])
                    ->mutateRecordDataUsing(fn (array $data): array => [...$data, 'title' => $data['title'].' (kopie)', 'is_published' => false]),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [RelationManagers\TicketTypesRelationManager::class, RelationManagers\OrdersRelationManager::class];
    }

    public static function publicUrl(Event $record): string
    {
        return route('events.show', $record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
