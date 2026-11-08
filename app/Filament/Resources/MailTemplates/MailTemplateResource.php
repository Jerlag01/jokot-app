<?php

namespace App\Filament\Resources\MailTemplates;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\MailTemplates\Pages\EditMailTemplate;
use App\Filament\Resources\MailTemplates\Pages\ListMailTemplates;
use App\Models\MailTemplate;
use App\Support\MailTemplates;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

/**
 * Subject and text of every mail the website sends. {placeholders} are filled in per mail.
 */
class MailTemplateResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SETTINGS;

    protected static ?string $model = MailTemplate::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'e-mailsjabloon';

    protected static ?string $pluralModelLabel = 'e-mailsjablonen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelopeOpen;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Text::make(fn (MailTemplate $record): string => $record->definition()['description']),
            TextInput::make('subject')->label('Onderwerp')->required()->maxLength(255),
            MarkdownEditor::make('body')
                ->label('Tekst')
                ->required()
                ->toolbarButtons([['bold', 'italic', 'link'], ['heading', 'bulletList', 'orderedList'], ['undo', 'redo']])
                ->helperText('# Titel, **vet**, *schuin*. Een lege regel begint een nieuwe alinea; <br> forceert een nieuwe regel.'),
            Section::make('Invulvelden')
                ->compact()
                ->collapsible()
                ->schema([
                    Text::make(fn (MailTemplate $record): HtmlString => self::placeholderHelp($record)),
                ]),
        ]);
    }

    public static function placeholderHelp(MailTemplate $record): HtmlString
    {
        $definition = $record->definition();
        $list = fn (array $items): string => collect($items)->map(fn (string $label, string $key): string => '<code>{'.e($key).'}</code> '.e($label))->implode('<br>');

        $html = '<p style="margin-bottom:.5rem">Worden per mail ingevuld, ook in het onderwerp:</p><p>'.$list($definition['placeholders']).'</p>';

        if ($definition['blocks'] !== []) {
            $html .= '<p style="margin:.75rem 0 .5rem">Blokken (alleen op een eigen regel, niet in het onderwerp):</p><p>'.$list($definition['blocks']).'</p>';
        }

        return new HtmlString($html);
    }

    /**
     * Shows the mail as received, filled in with the most recent matching record.
     *
     * @param  (callable(): array{subject: string, body: string})|null  $unsaved  texts not saved yet (edit page)
     */
    public static function previewAction(?callable $unsaved = null): Action
    {
        return Action::make('preview')
            ->label('Voorbeeld')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading('Voorbeeld')
            ->modalWidth('4xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Sluiten')
            ->modalContent(function (MailTemplate $record) use ($unsaved): HtmlString {
                if ($unsaved) {
                    MailTemplates::$overrides[$record->key] = $unsaved();
                }

                try {
                    $mail = MailTemplates::sample($record->key);

                    if (! $mail) {
                        return new HtmlString('<p>Er is nog geen aanvraag, contract, factuur of bestelling om een voorbeeld mee te tonen.</p>');
                    }

                    $html = $mail->render();
                    $subject = $mail->envelope()->subject;
                } catch (Throwable $exception) {
                    return new HtmlString('<p style="color:#dc2626">Het voorbeeld lukt niet: '.e($exception->getMessage()).'</p>');
                } finally {
                    unset(MailTemplates::$overrides[$record->key]);
                }

                return new HtmlString('<p style="margin-bottom:.75rem"><strong>Onderwerp:</strong> '.e($subject).'</p>'
                    .'<iframe sandbox srcdoc="'.e($html).'" style="width:100%;height:65vh;border:1px solid #e5e7eb;border-radius:.5rem;background:#fff"></iframe>'
                    .'<p style="margin-top:.5rem;font-size:.85em;color:#6b7280">Ingevuld met de meest recente gegevens. Bijlagen worden niet getoond.</p>');
            });
    }

    public static function resetAction(): Action
    {
        return Action::make('reset')
            ->label('Standaardtekst terugzetten')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Onderwerp en tekst worden vervangen door de oorspronkelijke versie.')
            ->action(function (MailTemplate $record, $livewire): void {
                $definition = $record->definition();
                $record->update(['subject' => $definition['subject'], 'body' => $definition['body']]);

                if (method_exists($livewire, 'refreshFormData')) {
                    $livewire->refreshFormData(['subject', 'body']);
                }

                Notification::make()->success()->title('Standaardtekst teruggezet')->send();
            });
    }

    public static function table(Table $table): Table
    {
        $order = array_keys(MailTemplates::definitions());

        return $table
            ->modifyQueryUsing(fn ($query) => $query->whereIn('key', $order)->orderByRaw('FIELD(`key`, '.implode(',', array_fill(0, count($order), '?')).')', $order))
            ->paginated(false)
            ->columns([
                TextColumn::make('label')->label('Mail')->state(fn (MailTemplate $record): string => $record->definition()['label'])
                    ->description(fn (MailTemplate $record): string => $record->definition()['description']),
                TextColumn::make('subject')->label('Onderwerp')->wrap(),
                TextColumn::make('customised')->label('Aangepast')->badge()
                    ->state(fn (MailTemplate $record): string => $record->subject === $record->definition()['subject'] && $record->body === $record->definition()['body'] ? 'Standaard' : 'Aangepast')
                    ->color(fn (string $state): string => $state === 'Aangepast' ? 'warning' : 'gray'),
                TextColumn::make('updated_at')->label('Gewijzigd')->since(),
            ])
            ->recordActions([EditAction::make(), self::previewAction()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMailTemplates::route('/'),
            'edit' => EditMailTemplate::route('/{record}/edit'),
        ];
    }
}
