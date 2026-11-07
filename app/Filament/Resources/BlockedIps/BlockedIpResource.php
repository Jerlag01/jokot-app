<?php

namespace App\Filament\Resources\BlockedIps;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\BlockedIps\Pages\ManageBlockedIps;
use App\Filament\Resources\LoginAttempts\BlockedIpDurations;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Support\Permissions;
use App\Support\Security\GeoIp;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\IpUtils;
use UnitEnum;

/**
 * IP addresses and ranges without access to the site, set by hand or automatically after failed sign-ins.
 */
class BlockedIpResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SECURITY;

    protected static ?string $model = BlockedIp::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'geblokkeerd IP-adres';

    protected static ?string $pluralModelLabel = 'geblokkeerde IP-adressen';

    protected static ?string $navigationLabel = 'Geblokkeerde IP\'s';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNoSymbol;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Security;

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = BlockedIp::query()->active()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('ip')
                ->label('IP-adres of reeks')
                ->placeholder('203.0.113.7 of 203.0.113.0/24')
                ->helperText('Een reeks in CIDR-notatie blokkeert alle adressen erin.')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true)
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    [$address, $mask] = array_pad(explode('/', (string) $value, 2), 2, null);

                    if (! filter_var($address, FILTER_VALIDATE_IP) || ($mask !== null && (! ctype_digit($mask) || (int) $mask > (str_contains($address, ':') ? 128 : 32)))) {
                        $fail('Geen geldig IP-adres of geldige reeks.');

                        return;
                    }

                    if (IpUtils::checkIp((string) request()->ip(), (string) $value)) {
                        $fail('Dit is je eigen IP-adres ('.request()->ip().'): je zou jezelf buitensluiten.');
                    }
                }),
            DateTimePicker::make('blocked_until')->label('Geblokkeerd tot')->helperText('Leeg = permanent.')->seconds(false),
            TextInput::make('reason')->label('Reden')->maxLength(255)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with('creator'))
            ->columns([
                TextColumn::make('ip')->label('IP-adres')->searchable()->copyable()->weight('bold')
                    ->description(fn (BlockedIp $record): string => str_contains($record->ip, '/') ? 'Reeks' : GeoIp::label(app(GeoIp::class)->country($record->ip))),
                TextColumn::make('reason')->label('Reden')->placeholder('-')->wrap(),
                IconColumn::make('is_automatic')->label('Automatisch')->boolean()->trueIcon(Heroicon::OutlinedCpuChip)->falseIcon(Heroicon::OutlinedUser),
                TextColumn::make('blocked_until')->label('Tot')->dateTime('d/m/Y H:i')->placeholder('Permanent')
                    ->color(fn (BlockedIp $record): ?string => $record->isActive() ? null : 'gray')
                    ->description(fn (BlockedIp $record): ?string => $record->isActive() ? null : 'Verlopen'),
                TextColumn::make('attempts')->label('Mislukte logins')->state(fn (BlockedIp $record): int => str_contains($record->ip, '/') ? 0 : LoginAttempt::query()->where('ip', $record->ip)->where('successful', false)->count()),
                TextColumn::make('hits')->label('Geweigerd')->tooltip('Aantal keer dat dit adres de site probeerde te openen sinds de blokkering')->description(fn (BlockedIp $record): ?string => $record->last_hit_at?->diffForHumans()),
                TextColumn::make('created_at')->label('Sinds')->dateTime('d/m/Y H:i')->description(fn (BlockedIp $record): ?string => $record->creator?->name)->sortable(),
            ])
            ->filters([
                TernaryFilter::make('active')->label('Status')->trueLabel('Actief')->falseLabel('Verlopen')->default(true)
                    ->queries(
                        true: fn (Builder $query) => $query->active(),
                        false: fn (Builder $query) => $query->whereNotNull('blocked_until')->where('blocked_until', '<=', now()),
                    ),
                TernaryFilter::make('is_automatic')->label('Soort')->trueLabel('Automatisch')->falseLabel('Manueel'),
            ])
            ->recordActions([
                Action::make('extend')
                    ->label('Verlengen')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->schema([\Filament\Forms\Components\Select::make('minutes')->label('Blokkeren voor')->options(BlockedIpDurations::options())->default('0')->required()])
                    ->action(function (BlockedIp $record, array $data): void {
                        $record->update(['blocked_until' => (int) $data['minutes'] ? now()->addMinutes((int) $data['minutes']) : null]);
                        Notification::make()->success()->title('Blokkering aangepast')->send();
                    }),
                EditAction::make(),
                DeleteAction::make()->label('Deblokkeren')->icon(Heroicon::OutlinedLockOpen)->modalHeading(fn (BlockedIp $record): string => 'IP '.$record->ip.' deblokkeren'),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->label('Deblokkeren')])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBlockedIps::route('/')];
    }
}
