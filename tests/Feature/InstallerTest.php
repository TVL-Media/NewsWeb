<?php

use App\Support\Install\EnvFile;
use App\Support\Install\Installer;
use App\Support\Install\Requirements;

// The test app normally counts as installed (phpunit.xml). These tests switch that off and use
// a lock file and .env in a temporary folder, so the project's real files are never touched.
beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/ngn-installer-'.uniqid();
    mkdir($this->dir);
    config([
        'installer.assume_installed' => false,
        'installer.lock_path' => $this->dir.'/installed',
        'installer.env_path' => $this->dir.'/.env',
    ]);
    Installer::refresh();
});

afterEach(function () {
    array_map('unlink', array_filter(glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
    @rmdir($this->dir);
    Installer::refresh();
});

it('sends every page to the installer until the app is installed', function () {
    $this->get('/')->assertRedirect('/install');
    $this->get('/explore')->assertRedirect('/install');
    $this->get('/admin/login')->assertRedirect('/install');
    $this->get('/install')->assertRedirect('/install/welcome');
    $this->get('/install/welcome')->assertOk()->assertSee('set up your news site');
});

it('does not let anyone skip ahead to a later step', function () {
    $this->get('/install/admin')->assertRedirect('/install/welcome');
    $this->post('/install/admin', ['name' => 'X'])->assertForbidden();
    $this->postJson('/install/run/migrate')->assertForbidden();
});

it('shows the server requirements', function () {
    $this->withSession(['install.reached' => 1])->get('/install/requirements')
        ->assertOk()->assertSee('PHP 8.3.0 or newer')->assertSee('pdo_mysql')->assertSee('FFmpeg');
    expect(Requirements::passes(Requirements::php()))->toBeTrue();
});

it('explains wrong database details instead of failing', function () {
    $this->postJson('/install/database/test', ['db_host' => '127.0.0.1', 'db_port' => 1, 'db_database' => 'news', 'db_username' => 'root'])
        ->assertOk()->assertJson(['ok' => false])->assertJsonFragment(['message' => 'Could not reach the database server. Check the host and port (usually 127.0.0.1 or localhost, port 3306).']);
    $this->postJson('/install/database/test', ['db_host' => '127.0.0.1', 'db_port' => 3306, 'db_database' => 'bad name;', 'db_username' => 'root'])
        ->assertJson(['ok' => false]);
});

it('is gone (404) once installed, and the site works', function () {
    Installer::lock();

    $this->get('/install')->assertNotFound();
    $this->get('/install/welcome')->assertNotFound();
    $this->postJson('/install/run/finish')->assertNotFound();
    $this->get('/')->assertOk();
    expect(Installer::lockInfo())->toHaveKeys(['version', 'installed_at'])
        ->and(Installer::lockInfo()['version'])->toBe(config('app-version.version'));
});

it('recognises a site that was installed before the installer existed', function () {
    // Tables + an admin in the database, and a .env: lock it automatically, never show the installer.
    file_put_contents($this->dir.'/.env', "APP_NAME=Existing\n");
    \App\Models\Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'Secret12345']);

    $this->get('/')->assertOk();
    expect(is_file($this->dir.'/installed'))->toBeTrue()
        ->and(Installer::lockInfo()['detected'])->toBeTrue();
    $this->get('/install')->assertNotFound();
});

it('does not lock an install that is still running', function () {
    file_put_contents($this->dir.'/.env', "APP_NAME=Half\n");
    \App\Models\Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'Secret12345']);
    Installer::markInstalling();

    $this->get('/')->assertRedirect('/install');
    expect(is_file($this->dir.'/installed'))->toBeFalse();
});

it('never mistakes the example database settings for an existing install', function () {
    // A fresh upload: no .env yet, while the default database from .env.example already holds another site.
    \App\Models\Admin::create(['name' => 'Someone else', 'email' => 'other@example.test', 'password' => 'Secret12345']);
    config(['app.key' => null]);

    Installer::prepareRuntime(); // first request: .env is created from .env.example
    expect(is_file($this->dir.'/.env'))->toBeTrue();

    Installer::refresh();
    $this->get('/')->assertRedirect('/install');
    expect(is_file($this->dir.'/installed'))->toBeFalse();
});

it('writes .env values safely', function () {
    $env = $this->dir.'/.env';
    file_put_contents($env, "# comment\nAPP_NAME=Old\nDB_PASSWORD=\n");
    EnvFile::set($env, ['APP_NAME' => 'My "News" #1', 'DB_PASSWORD' => 'p$ss word', 'NEW_KEY' => 'yes']);

    $values = \Dotenv\Dotenv::parse(file_get_contents($env));
    expect($values['APP_NAME'])->toBe('My "News" #1')
        ->and($values['DB_PASSWORD'])->toBe('p$ss word')
        ->and($values['NEW_KEY'])->toBe('yes')
        ->and(file_get_contents($env))->toContain('# comment');
});
