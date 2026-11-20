<?php

namespace App\Console\Commands;

use App\Support\Audit;
use App\Support\Install\Installer;
use App\Support\Install\Updater;
use Illuminate\Console\Command;

/** Same as Admin → System update, from SSH (see UPGRADE.md). */
class UpdateCommand extends Command
{
    protected $signature = 'nextgennews:update {--force : Do not ask for confirmation}';

    protected $description = 'Apply a new NextGenNews version: maintenance mode, database changes, caches, back online';

    public function handle(Updater $updater): int
    {
        if (! Installer::isInstalled()) {
            $this->components->error('NextGenNews is not installed yet. Run php artisan nextgennews:install first.');

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Running now', Updater::installedVersion());
        $this->components->twoColumnDetail('Files on the server', Updater::codeVersion());
        $this->components->twoColumnDetail('Database changes waiting', (string) count(Updater::pendingMigrations()));

        if (! $this->option('force') && ! $this->confirm('Have you backed up the database and files? Run the update now?', false)) {
            return self::FAILURE;
        }

        $result = $updater->run();
        foreach ($result['steps'] as $s) {
            $this->components->twoColumnDetail($s['label'], $s['ok'] ? '<fg=green>'.$s['message'].'</>' : '<fg=red>'.$s['message'].'</>');
        }
        Audit::log('system.update', null, ['version' => $result['from']], ['version' => $result['to'], 'ok' => $result['ok']], label: 'System update (command line)');

        if (! $result['ok']) {
            $this->components->error('The update stopped. Fix the problem above and run it again; finished changes are not repeated.');

            return self::FAILURE;
        }
        $this->components->info('Update complete: version '.$result['to'].'.');

        return self::SUCCESS;
    }
}
