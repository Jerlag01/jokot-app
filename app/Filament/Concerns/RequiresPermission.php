<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Resource access by role permission: the resource sets static $permission (one of App\Support\Permissions).
 */
trait RequiresPermission
{
    public static function userCan(?string $permission = null): bool
    {
        return (bool) auth()->user()?->hasPermission($permission ?? static::$permission);
    }

    public static function canViewAny(): bool
    {
        return static::userCan();
    }

    public static function canView(Model $record): bool
    {
        return static::userCan();
    }

    public static function canCreate(): bool
    {
        return static::userCan();
    }

    public static function canEdit(Model $record): bool
    {
        return static::userCan();
    }

    public static function canDelete(Model $record): bool
    {
        return static::userCan();
    }

    public static function canDeleteAny(): bool
    {
        return static::userCan();
    }
}
