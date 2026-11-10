<?php

use App\Models\Admin;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Create the panel's permissions and roles, and give staff who existed before the panel
 * the matching role (website admin → Super Admin, chief → Chief Editor, editor → Editor).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PermissionSeeder)->run();

        foreach (Admin::withTrashed()->get() as $admin) {
            if ($admin->roles()->count() === 0) {
                $admin->assignRole(PermissionSeeder::roleFor($admin->role));
            }
        }
    }

    public function down(): void
    {
        // Roles stay; dropping the permission tables removes them.
    }
};
