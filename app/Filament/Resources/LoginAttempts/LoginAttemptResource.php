<?php

namespace App\Filament\Resources\LoginAttempts;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\LoginAttempts\Pages\ListLoginAttempts;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Support\Permissions;
use App\Support\Security\GeoIp;
use App\Support\Security\IpBlocker;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Every sign-in on the admin site and the check-in app: who tried which login, from which IP and country.
 */
class LoginAttemptResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::SECURITY;

    protected static ?string $model = LoginAttempt::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'aanmeldpoging';

    protected static ?string $pluralModelLabel = 'aanmeldpogingen';

    protected static ?string $navigationLabel = 'Aanmeldpogingen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Security;

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = LoginAttempt::query()->where('successful', false)->where('created_at', '>=', now()->subDay())->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Mislukte aanmeldingen in de laatste 24 uur';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Tijdstip')->dateTime('d/m/Y H:i:s')->sortable(),
                IconColumn::make('successful')->label('Gelukt')->boolean(),
                TextColumn::make('email')->label('Login (e-mail)')->searchable()->description(fn (LoginAttempt $record): ?string => $record->user?->name)->copyable(),
                TextColumn::make('reason')->label('Reden')->placeholder('-')->color('danger'),
                TextColumn::make('ip')->label('IP-adres')->searchable()->copyable()
                    ->description(fn (LoginAttempt $record): ?string => app(IpBlocker::class)->blockFor($record->ip) ? 'Geblokkeerd' : null),
                TextColumn::make('country_code')->label('Land')->formatStateUsing(fn (?string $state): string => GeoIp::label($state))->placeholder('Onbekend')->sortable(),
                TextColumn::make('channel')->label('Via')->badge()->formatStateUsing(fn (string $state): string => $state === 'api' ? 'Check-in-app' : 'Beheer')->color(fn (string $state): string => $state === 'api' ? 'info' : 'gray'),
                TextColumn::make('user_agent')->label('Browser')->limit(40)->tooltip(fn (LoginAttempt $record): ?string => $record->user_agent)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('successful')->label('Resultaat')->trueLabel('Gelukt')->falseLabel('Mislukt'),
                SelectFilter::make('channel')->label('Via')->options(['admin' => 'Beheer', 'api' => 'Check-in-app']),
                SelectFilter::make('country_code')->label('Land')->searchable()
                    ->options(fn (): array => LoginAttempt::query()->distinct()->pluck('country_code')->mapWithKeys(fn (?string $code): array => [(string) $code => GeoIp::label($code)])->sort()->all())
                    ->query(fn (Builder $query, array $data) => $query->when(array_key_exists('value', $data) && $data['value'] !== null, fn (Builder $query) => $data['value'] === '' ? $query->whereNull('country_code') : $query->where('country_code', $data['value']))),
                Filter::make('period')->schema([
                    DatePicker::make('from')->label('Vanaf'),
                    DatePicker::make('until')->label('Tot en met'),
                ])->query(fn (Builder $query, array $data) => $query
                    ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                    ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ])
            ->recordActions([
                Action::make('block')
                    ->label('Blokkeer IP')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (LoginAttempt $record): bool => $record->ip !== request()->ip() && ! BlockedIp::query()->active()->where('ip', $record->ip)->exists())
                    ->modalHeading(fn (LoginAttempt $record): string => 'IP '.$record->ip.' blokkeren')
                    ->schema([
                        TextInput::make('reason')->label('Reden')->default(fn (LoginAttempt $record): string => 'Aanmeldpogingen op '.($record->email ?? '?')),
                        Select::make('minutes')->label('Hoe lang')->options(BlockedIpDurations::options())->default('0')->required(),
                    ])
                    ->action(function (LoginAttempt $record, array $data): void {
                        app(IpBlocker::class)->block($record->ip, $data['reason'] ?? null, (int) $data['minutes'] ?: null, auth()->user());
                        Notification::make()->success()->title('IP '.$record->ip.' geblokkeerd')->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginAttempts::route('/')];
    }
}
