<?php

namespace App\Support\Install;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Story;
use App\Support\Settings;
use Database\Seeders\InstallSeeder;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The installation itself, one task at a time (the web installer calls one task per request and shows a
 * progress list; `php artisan nextgennews:install` runs them all). Every task can be run again after a
 * failure without harm, and the site only goes live when the last task writes the lock file, so a failed
 * install never leaves a half-working site behind.
 *
 * $data = [
 *   'db'    => [host, port, database, username, password],
 *   'site'  => [name, url, tagline, locale, timezone, env],         // env: production | local
 *   'admin' => [name, email, password_hash],
 *   'keys'  => [ai_key, tts_provider, tts_google_key, tts_azure_key, tts_azure_region, sms_key, sms_sender, sms_template],
 *   'sample' => bool, 'license' => ?string,
 * ]
 */
class InstallRunner
{
    /** @return array<string, string> task => label, in order */
    public static function tasks(bool $sample): array
    {
        return array_filter([
            'env' => 'Write the .env file',
            'key' => 'Generate the app key',
            'migrate' => 'Create the database tables',
            'seed' => 'Add roles, categories, cities and pages',
            'sample' => $sample ? 'Add the sample stories, polls and quiz' : null,
            'admin' => 'Create your admin account',
            'settings' => 'Save your site settings and keys',
            'storage' => 'Link the storage folder',
            'cache' => 'Build the caches',
            'finish' => 'Finish and lock the installer',
        ]);
    }

    /** @return array{ok: bool, message: string} */
    public function run(string $task, array $data): array
    {
        @set_time_limit(0);
        Installer::markInstalling();
        $this->useDatabase($data['db']);

        try {
            $message = match ($task) {
                'env' => $this->env($data),
                'key' => $this->key(),
                'migrate' => $this->migrate(),
                'seed' => $this->seed(),
                'sample' => $this->sample(),
                'admin' => $this->admin($data['admin']),
                'settings' => $this->settings($data),
                'storage' => $this->storage(),
                'cache' => $this->cache(),
                'finish' => $this->finish(),
            };

            return ['ok' => true, 'message' => $message];
        } catch (\Throwable $e) {
            report($e);

            return ['ok' => false, 'message' => $e instanceof \PDOException || str_contains($e->getMessage(), 'SQLSTATE')
                ? DatabaseCheck::friendly($e)
                : $e->getMessage()];
        }
    }

    /** Point this request at the database from the Database step (the .env may not be loaded yet). */
    public function useDatabase(array $db): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $db['host'],
            'database.connections.mysql.port' => (int) $db['port'],
            'database.connections.mysql.database' => $db['database'],
            'database.connections.mysql.username' => $db['username'],
            'database.connections.mysql.password' => (string) ($db['password'] ?? ''),
            'database.connections.mysql.url' => null,
        ]);
        DB::purge('mysql');
    }

    private function env(array $data): string
    {
        $site = $data['site'];
        $local = ($site['env'] ?? 'production') === 'local';
        $path = config('installer.env_path');
        if (! is_file($path) && is_file(base_path('.env.example'))) {
            copy(base_path('.env.example'), $path);
        }
        EnvFile::set($path, [
            'APP_NAME' => $site['name'],
            'APP_ENV' => $local ? 'local' : 'production',
            'APP_DEBUG' => $local,
            'APP_URL' => rtrim($site['url'], '/'),
            'TIMEZONE' => $site['timezone'],
            'APP_LOCALE' => $site['locale'],
            'LOG_LEVEL' => $local ? 'debug' : 'error',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db']['host'],
            'DB_PORT' => (int) $data['db']['port'],
            'DB_DATABASE' => $data['db']['database'],
            'DB_USERNAME' => $data['db']['username'],
            'DB_PASSWORD' => (string) ($data['db']['password'] ?? ''),
            // Works on any host: no Redis needed. (Switch to Redis later for big traffic, see INSTALL.md.)
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'database',
            'PURCHASE_CODE' => $data['license'] ?? null,
        ]);
        foreach (['config', 'routes-v7', 'events', 'services', 'packages'] as $f) {
            @unlink(base_path("bootstrap/cache/{$f}.php"));
        }

        return 'Saved '.basename($path).'.';
    }

    private function key(): string
    {
        $path = config('installer.env_path');
        $current = $this->envValue($path, 'APP_KEY');
        if (blank($current)) {
            // Keep the key this install session already uses (its cookies are encrypted with it).
            $current = config('app.key') ?: 'base64:'.base64_encode(random_bytes(32));
            EnvFile::set($path, ['APP_KEY' => $current]);
        }
        config(['app.key' => $current]);
        @unlink(storage_path('installer.key'));

        return 'App key is set.';
    }

    private function migrate(): string
    {
        Artisan::call('migrate', ['--force' => true]);

        return 'Tables are ready.';
    }

    private function seed(): string
    {
        if (Category::exists()) {
            return 'Already added.';
        }
        DB::transaction(fn () => Artisan::call('db:seed', ['--class' => InstallSeeder::class, '--force' => true]));

        return Category::count().' categories added.';
    }

    private function sample(): string
    {
        if (Story::exists()) {
            return 'Already added.';
        }
        DB::transaction(fn () => Artisan::call('db:seed', ['--class' => SampleDataSeeder::class, '--force' => true]));

        return Story::count().' sample stories added.';
    }

    private function admin(array $admin): string
    {
        $password = $admin['password_hash'] ?? Hash::make($admin['password']);
        $model = Admin::withTrashed()->updateOrCreate(
            ['email' => strtolower(trim($admin['email']))],
            ['name' => $admin['name'], 'password' => $password, 'role' => 'admin', 'status' => 'active', 'deleted_at' => null],
        );
        $model->syncRoles(['Super Admin']);

        return 'Admin account '.$model->email.' is ready.';
    }

    private function settings(array $data): string
    {
        $site = $data['site'];
        $keys = $data['keys'] ?? [];
        Settings::set('site.name', $site['name']);
        Settings::set('site.locale', $site['locale']);
        Settings::set('site.timezone', $site['timezone']);
        if (filled($site['tagline'] ?? null)) {
            Settings::set('site.tagline', $site['tagline']);
        }
        Settings::set('site.contact_email', strtolower(trim($data['admin']['email'])));
        if (($site['env'] ?? 'production') === 'local') {
            // Testing on a computer: no authenticator app needed. Live sites keep two-step sign-in for the Super Admin.
            Settings::set('security.enforce_2fa_roles', []);
            Settings::set('sms.dev_otp', true); // every reader can log in with OTP 123456 (ignored on a live site)
        }

        $saved = 0;
        foreach ([
            'ai_key' => 'ai.api_key', 'tts_google_key' => 'tts.google_key', 'tts_azure_key' => 'tts.azure_key',
            'tts_azure_region' => 'tts.azure_region', 'sms_key' => 'sms.api_key', 'sms_sender' => 'sms.sender_id', 'sms_template' => 'sms.template_id',
        ] as $field => $key) {
            if (filled($keys[$field] ?? null)) {
                Settings::set($key, trim($keys[$field]));
                $saved++;
            }
        }
        $tts = $keys['tts_provider'] ?? null;
        if (in_array($tts, ['google', 'azure'], true)) {
            Settings::set('tts.provider', $tts);
        }
        if (filled($keys['sms_key'] ?? null)) {
            Settings::set('sms.provider', 'msg91');
        }

        return $saved ? "Settings saved, with {$saved} API ".str('key')->plural($saved).'.' : 'Settings saved. Add API keys any time in Admin → Settings.';
    }

    private function storage(): string
    {
        if (file_exists(public_path('storage')) /* also a Windows junction */) {
            return 'Already linked.';
        }
        try {
            Artisan::call('storage:link');
        } catch (\Throwable) {
        }

        return file_exists(public_path('storage')) /* also a Windows junction */
            ? 'public/storage now points to storage/app/public.'
            : 'Could not create the link (this host does not allow it). Photos will not show until you create it: see INSTALL.md → "Storage link".';
    }

    private function cache(): string
    {
        Artisan::call('optimize:clear');
        Artisan::call('view:cache');

        return 'Caches rebuilt.';
    }

    private function finish(): string
    {
        Installer::lock();

        return 'Installed version '.config('app-version.version').'.';
    }

    private function envValue(string $path, string $key): ?string
    {
        if (! is_file($path) || ! preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', (string) file_get_contents($path), $m)) {
            return null;
        }

        return trim($m[1], " \t\"'\r") ?: null;
    }
}
