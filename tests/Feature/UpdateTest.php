<?php

use App\Models\Admin;
use App\Models\AuditLog;
use App\Support\Install\Installer;
use App\Support\Install\Updater;
use App\Support\Settings;
use Database\Seeders\InstallSeeder;

/*
| Milestone 3: Admin → System update and `php artisan nextgennews:update`.
| The lock file lives in a temporary folder and maintenance mode uses the array cache,
| so the project's real site is never switched off by these tests.
*/

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ngn-update-'.uniqid();
    mkdir($this->dir);
    config([
        'installer.lock_path' => $this->dir.'/installed',
        'app.maintenance.driver' => 'cache',
        'app.maintenance.store' => 'array',
    ]);
    file_put_contents($this->dir.'/installed', json_encode(['version' => '0.9.0', 'installed_at' => '2026-01-01T10:00:00+05:30']));

    $this->seed(InstallSeeder::class);
    Settings::set('security.enforce_2fa_roles', []);
    $this->admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'Secret!2026x']);
    $this->admin->assignRole('Super Admin');
});

afterEach(function () {
    @unlink($this->dir.'/installed');
    @rmdir($this->dir);
});

it('shows the versions and the backup warning', function () {
    $this->actingAs($this->admin, 'admin')->get('/admin/system/update')->assertOk()
        ->assertSee('Update ready')->assertSee('0.9.0')->assertSee(config('app-version.version'))
        ->assertSee('Back up first');
});

it('runs the update, keeps the data and brings the site back', function () {
    Settings::set('site.name', 'Kept Times');
    $categories = \App\Models\Category::count();

    $this->actingAs($this->admin, 'admin')->post('/admin/system/update', ['backup' => 1, 'confirm_password' => 'Secret!2026x'])
        ->assertRedirect('/admin/system/update')->assertSessionHas('success');

    expect(Updater::installedVersion())->toBe(config('app-version.version'))
        ->and(Installer::lockInfo()['installed_at'])->toBe('2026-01-01T10:00:00+05:30') // original install date kept
        ->and(app()->maintenanceMode()->active())->toBeFalse()
        ->and(\App\Models\Category::count())->toBe($categories)
        ->and(setting('site.name'))->toBe('Kept Times')
        ->and(AuditLog::where('action', 'system.update')->exists())->toBeTrue();

    $this->get('/admin/system/update')->assertSee('Up to date');
});

it('needs the backup box and the password', function () {
    $this->actingAs($this->admin, 'admin')->post('/admin/system/update', ['confirm_password' => 'Secret!2026x'])
        ->assertSessionHasErrors('backup');
    $this->actingAs($this->admin, 'admin')->post('/admin/system/update', ['backup' => 1, 'confirm_password' => 'wrong'])
        ->assertSessionHasErrors('confirm_password');
    expect(Updater::installedVersion())->toBe('0.9.0');
});

it('is only for admins who manage settings', function () {
    $editor = Admin::create(['name' => 'Ed', 'email' => 'ed@example.test', 'password' => 'Secret!2026x']);
    $editor->assignRole('Editor');

    $this->actingAs($editor, 'admin')->get('/admin/system/update')->assertForbidden();
    $this->actingAs($editor, 'admin')->post('/admin/system/update', ['backup' => 1, 'confirm_password' => 'Secret!2026x'])->assertForbidden();
});

it('updates from the command line too', function () {
    $this->artisan('nextgennews:update', ['--force' => true])->expectsOutputToContain('Update complete')->assertSuccessful();
    expect(Updater::installedVersion())->toBe(config('app-version.version'));
});
