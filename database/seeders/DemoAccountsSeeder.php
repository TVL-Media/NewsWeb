<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Support\Settings;
use Illuminate\Database\Seeder;

/**
 * Development only: three staff accounts with the password "password" (Super Admin, Chief Editor,
 * Editor). Never run by the installer, and refused on a production server.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped demo staff accounts: this is a production server.');

            return;
        }

        Admin::create(['name' => 'Site Admin', 'email' => 'admin@example.test', 'password' => 'password', 'role' => 'admin'])->assignRole('Super Admin');
        Admin::create(['name' => 'Chief Editor', 'email' => 'chief@example.test', 'password' => 'password', 'role' => 'chief'])->assignRole('Chief Editor');
        Admin::create(['name' => 'Desk Editor', 'email' => 'editor@example.test', 'password' => 'password', 'role' => 'editor'])->assignRole('Editor');
        // Don't force two-factor on these local accounts. Turn it back on in Settings → Security.
        Settings::set('security.enforce_2fa_roles', []);
    }
}
