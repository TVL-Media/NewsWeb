<?php

namespace App\Filament\Concerns;

/**
 * Role gate for Filament resources/pages. Set `protected static array $roles` on the class.
 * editor: newsroom + daily + moderation · chief: + feeds, auto-publish settings · admin: everything.
 */
trait RestrictsByRole
{
    public static function allowedRole(): bool
    {
        $user = auth('admin')->user();

        return $user && in_array($user->role, static::$roles, true);
    }

    public static function canViewAny(): bool
    {
        return static::allowedRole();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::allowedRole();
    }
}
