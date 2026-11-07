<?php

namespace App\Filament\Resources\Roles;

use App\Enums\NavigationGroup;
use App\Filament\Concerns\RequiresPermission;
use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use App\Support\Permissions;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RoleResource extends Resource
{
    use RequiresPermission;

    protected static string $permission = Permissions::USERS;

    protected static ?string $model = Role::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $modelLabel = 'rol';

    protected static ?string $pluralModelLabel = 'rollen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::System;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Naam')->required()->maxLength(100)->unique(ignoreRecord: true),
            TextInput::make('description')->label('Omschrijving')->maxLength(255),
            Toggle::make('is_admin')->label('Beheerder (alle rechten)')->live()->columnSpanFull(),
            CheckboxList::make('permissions')
                ->label('Rechten')
                ->options(self::permissionOptions())
                ->columns(2)
                ->bulkToggleable()
                ->columnSpanFull()
                ->hidden(fn (Get $get): bool => (bool) $get('is_admin'))
                ->dehydrateStateUsing(fn (?array $state, Get $get): array => $get('is_admin') ? [] : array_values(array_intersect(Permissions::all(), $state ?? []))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function permissionOptions(): array
    {
        $options = [];

        foreach (Permissions::grouped() as $group => $permissions) {
            foreach ($permissions as $key => $label) {
                $options[$key] = "{$group}: {$label}";
            }
        }

        return $options;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('users'))
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Naam')->description(fn (Role $record): ?string => $record->description)->searchable(),
                IconColumn::make('is_admin')->label('Beheerder')->boolean(),
                TextColumn::make('permissions')->label('Rechten')
                    ->state(fn (Role $record): string => $record->is_admin ? 'Alle' : count($record->permissions ?? []).' / '.count(Permissions::all()))
                    ->badge()->color('gray'),
                TextColumn::make('users_count')->label('Gebruikers')->numeric(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        // The last admin role cannot go, and a role in use cannot either.
        return static::userCan()
            && $record->users()->doesntExist()
            && ! ($record->is_admin && Role::query()->where('is_admin', true)->count() === 1);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
