<?php

use App\Support\Install\InstallRunner;
use App\Support\Install\Installer;
use Illuminate\Support\Facades\DB;

/*
| Full installation on an empty MySQL database, exactly as /install and `nextgennews:install` do it.
| Uses its own database (ngn_install_test, created and dropped here) and a temporary .env and lock file.
| Skipped when no MySQL server is reachable. Set INSTALL_TEST_DB_HOST / _PORT / _USERNAME / _PASSWORD to use another server.
*/

function installTestDb(): array
{
    return [
        'host' => env('INSTALL_TEST_DB_HOST', '127.0.0.1'), 'port' => (int) env('INSTALL_TEST_DB_PORT', 3306),
        'database' => 'ngn_install_test', 'username' => env('INSTALL_TEST_DB_USERNAME', 'root'), 'password' => (string) env('INSTALL_TEST_DB_PASSWORD', ''),
    ];
}

function installTestServer(): ?PDO
{
    $db = installTestDb();
    try {
        return new PDO("mysql:host={$db['host']};port={$db['port']}", $db['username'], $db['password'], [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    } catch (\Throwable) {
        return null;
    }
}

beforeEach(function () {
    $this->defaultConnection = config('database.default');
    $this->server = installTestServer();
    if (! $this->server) {
        $this->markTestSkipped('No MySQL server for the fresh-install test.');
    }
    $this->server->exec('DROP DATABASE IF EXISTS ngn_install_test');
    $this->server->exec('CREATE DATABASE ngn_install_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    $this->dir = sys_get_temp_dir().'/ngn-fresh-'.uniqid();
    mkdir($this->dir);
    config([
        'installer.assume_installed' => false,
        'installer.lock_path' => $this->dir.'/installed',
        'installer.env_path' => $this->dir.'/.env',
    ]);
    Installer::refresh();
});

afterEach(function () {
    // The installer pointed the app at MySQL; the test database (and its rollback) is the default one again.
    config(['database.default' => $this->defaultConnection]);
    if ($this->server ?? null) {
        DB::purge('mysql');
        $this->server->exec('DROP DATABASE IF EXISTS ngn_install_test');
    }
    if (isset($this->dir)) {
        array_map('unlink', array_filter(glob($this->dir.'/{,.}*', GLOB_BRACE) ?: [], 'is_file'));
        @rmdir($this->dir);
    }
    Installer::refresh();
});

it('installs on an empty database, step by step', function () {
    $data = [
        'db' => installTestDb(),
        'site' => ['name' => 'Fresh Times', 'url' => 'https://news.example.com', 'tagline' => 'Fresh news', 'locale' => 'hi', 'timezone' => 'Asia/Kolkata', 'env' => 'production'],
        'admin' => ['name' => 'Owner', 'email' => 'Owner@Example.com', 'password' => 'Secret12345'],
        'keys' => ['ai_key' => 'sk-ant-fresh-1234'],
        'sample' => false,
        'license' => null,
    ];

    // Not installed yet: the site sends visitors to the installer.
    $this->get('/')->assertRedirect('/install');

    $runner = app(InstallRunner::class);
    $log = [];
    foreach (InstallRunner::tasks(false) as $task => $label) {
        $result = $runner->run($task, $data);
        $log[] = sprintf('%-45s %s %s', $label, $result['ok'] ? 'OK  ' : 'FAIL', $result['message']);
        expect($result['ok'])->toBeTrue("{$label} failed: {$result['message']}");
    }
    fwrite(STDOUT, "\n  Fresh install on an empty MySQL database:\n    ".implode("\n    ", $log)."\n");

    // The lock file, the .env and the database are all in place.
    expect(Installer::lockInfo()['version'])->toBe(config('app-version.version'))
        ->and(is_file($this->dir.'/installing'))->toBeFalse();
    $env = \Dotenv\Dotenv::parse(file_get_contents($this->dir.'/.env'));
    expect($env)->toMatchArray(['APP_NAME' => 'Fresh Times', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'DB_DATABASE' => 'ngn_install_test', 'SESSION_DRIVER' => 'database', 'QUEUE_CONNECTION' => 'database'])
        ->and($env['APP_KEY'])->toStartWith('base64:');

    $mysql = DB::connection('mysql');
    $admin = \App\Models\Admin::on('mysql')->where('email', 'owner@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('Super Admin'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Hash::check('Secret12345', $admin->password))->toBeTrue()
        ->and($mysql->table('categories')->count())->toBeGreaterThan(0)
        ->and($mysql->table('stories')->count())->toBe(0) // no sample data was asked for
        ->and($mysql->table('users')->count())->toBe(0);

    $saved = $mysql->table('settings')->pluck('value', 'key');
    expect(json_decode($saved['site.name']))->toBe('Fresh Times')
        ->and(json_decode($saved['site.locale']))->toBe('hi')
        ->and($saved['ai.api_key'])->not->toContain('sk-ant-fresh') // stored encrypted
        ->and((bool) $mysql->table('settings')->where('key', 'ai.api_key')->value('is_encrypted'))->toBeTrue();

    // Installed: the installer is gone.
    Installer::refresh();
    $this->get('/install')->assertNotFound();
});

it('can be run again after a failure without harm', function () {
    $data = [
        'db' => installTestDb(),
        'site' => ['name' => 'Retry Times', 'url' => 'https://news.example.com', 'locale' => 'en', 'timezone' => 'Asia/Kolkata', 'env' => 'production'],
        'admin' => ['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'Secret12345'],
        'keys' => [], 'sample' => false, 'license' => null,
    ];
    $runner = app(InstallRunner::class);
    foreach (['env', 'key', 'migrate', 'seed'] as $task) {
        expect($runner->run($task, $data)['ok'])->toBeTrue();
    }
    $categories = DB::connection('mysql')->table('categories')->count();

    // Stopped here (say the browser was closed): the site is not live yet...
    Installer::refresh();
    $this->get('/')->assertRedirect('/install');

    // ...and running the same steps again neither fails nor duplicates anything.
    foreach (['migrate', 'seed', 'admin', 'admin'] as $task) {
        expect($runner->run($task, $data)['ok'])->toBeTrue();
    }
    expect(DB::connection('mysql')->table('categories')->count())->toBe($categories)
        ->and(DB::connection('mysql')->table('admins')->count())->toBe(1);
});

it('refuses a database that already has data', function () {
    $this->server->exec('CREATE TABLE ngn_install_test.customers (id int)');

    $check = \App\Support\Install\DatabaseCheck::test(installTestDb());
    expect($check['ok'])->toBeFalse()->and($check['message'])->toContain('already has 1 tables');
});
