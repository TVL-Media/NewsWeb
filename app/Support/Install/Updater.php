<?php

namespace App\Support\Install;

use Illuminate\Support\Facades\Artisan;

/**
 * Applies a new version after its files were uploaded: maintenance mode on, run the new database
 * migrations, rebuild the caches, record the version, site back up. Only migrations run: no seeders,
 * so stories, readers, settings and uploads are never changed or deleted.
 */
class Updater
{
    /** The version recorded at install / last update. */
    public static function installedVersion(): string
    {
        return Installer::lockInfo()['version'] ?? '0.0.0';
    }

    /** The version of the files on the server (config/app-version.php). */
    public static function codeVersion(): string
    {
        return config('app-version.version');
    }

    /** @return list<string> migration names not run yet */
    public static function pendingMigrations(): array
    {
        $migrator = app('migrator');
        if (! $migrator->repositoryExists()) {
            return [];
        }
        $files = $migrator->getMigrationFiles([database_path('migrations'), ...$migrator->paths()]);

        return array_values(array_diff(array_keys($files), $migrator->getRepository()->getRan()));
    }

    public static function needed(): bool
    {
        return version_compare(self::codeVersion(), self::installedVersion(), '>') || self::pendingMigrations() !== [];
    }

    /** @return array{ok: bool, steps: list<array{label: string, ok: bool, message: string}>} */
    public function run(): array
    {
        @set_time_limit(0);
        $steps = [];
        $step = function (string $label, callable $fn) use (&$steps): bool {
            try {
                $steps[] = ['label' => $label, 'ok' => true, 'message' => (string) ($fn() ?? 'Done.')];

                return true;
            } catch (\Throwable $e) {
                report($e);
                $steps[] = ['label' => $label, 'ok' => false, 'message' => $e->getMessage()];

                return false;
            }
        };

        $from = self::installedVersion();
        $to = self::codeVersion();
        $pending = count(self::pendingMigrations());

        $ok = $step('Put the site in maintenance mode', fn () => Artisan::call('down', ['--retry' => 60]) === 0 ? 'Visitors see a short "back soon" page.' : throw new \RuntimeException('Could not switch maintenance mode on.'));
        try {
            $ok = $ok && $step('Update the database', function () use ($pending) {
                Artisan::call('migrate', ['--force' => true]);

                return $pending ? "{$pending} database ".str('change')->plural($pending).' applied.' : 'The database was already up to date.';
            });
            $ok = $ok && $step('Rebuild the caches', function () {
                Artisan::call('optimize:clear');
                Artisan::call('view:cache');

                return 'Caches rebuilt.';
            });
            $ok = $ok && $step('Record the new version', function () use ($from, $to) {
                Installer::setVersion($to);

                return $from === $to ? "Version {$to}." : "{$from} → {$to}.";
            });
        } finally {
            // Whatever happened, never leave the site in maintenance mode.
            $step('Bring the site back online', fn () => Artisan::call('up') === 0 ? 'The site is live.' : throw new \RuntimeException('Run `php artisan up` on the server.'));
        }

        return ['ok' => $ok && collect($steps)->every('ok'), 'steps' => $steps, 'from' => $from, 'to' => $to];
    }
}
