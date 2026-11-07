<?php

namespace App\Filament\Resources\Users;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Role;
use App\Models\User;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Board members who can sign in to the admin site.
 */
class UserResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::USERS;

    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'gebruiker';

    protected static ?string $pluralModelLabel = 'gebruikers';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(255),
            TextInput::make('email')->label('E-mail')->email()->required()->unique(ignoreRecord: true),
            TextInput::make('password')
                ->label('Wachtwoord')
                ->password()
                ->revealable()
                ->minLength(8)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leeg laten om het wachtwoord niet te wijzigen.' : null),
            CheckboxList::make('roles')
                ->label('Rollen')
                ->relationship('roles', 'name')
                ->descriptions(fn (): array => Role::query()->pluck('description', 'id')->filter()->all())
                ->columns(2)
                ->columnSpanFull()
                ->required(),
            Toggle::make('is_active')
                ->label('Actief (mag aanmelden)')
                ->default(true)
                ->inline(false)
                ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naam')->searchable()->sortable(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('roles.name')->label('Rollen')->badge(),
                IconColumn::make('is_active')->label('Actief')->boolean(),
                TextColumn::make('created_at')->label('Sinds')->date('d/m/Y')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan() && ! $record->is(auth()->user());
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
