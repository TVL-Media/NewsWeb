<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\AdminNotification;

/** In-panel notifications (bell icon). */
class Notify
{
    public static function admin(?int $adminId, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        if (! $adminId || $adminId === auth('admin')->id()) {
            return;
        }
        AdminNotification::create(compact('type', 'title', 'body', 'url') + ['admin_id' => $adminId]);
    }

    public static function permission(string $permission, string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        Admin::where('status', 'active')->get()
            ->filter(fn (Admin $a) => $a->isSuperAdmin() || $a->can($permission))
            ->each(fn (Admin $a) => self::admin($a->id, $type, $title, $body, $url));
    }

    public static function superAdmins(string $type, string $title, ?string $body = null, ?string $url = null): void
    {
        Admin::role('Super Admin')->where('status', 'active')->get()
            ->each(fn (Admin $a) => self::admin($a->id, $type, $title, $body, $url));
    }
}
