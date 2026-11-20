<?php

namespace App\Console\Commands;

use App\Support\Install\DatabaseCheck;
use App\Support\Install\InstallRunner;
use App\Support\Install\Installer;
use App\Support\Install\Requirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;

/**
 * The web installer's steps from the terminal. Asks for anything not given as an option, or runs
 * unattended with --no-interaction and every option set. Same tasks as /install (InstallRunner).
 */
class InstallCommand extends Command
{
    protected $signature = 'nextgennews:install
        {--db-host=127.0.0.1} {--db-port=3306} {--db-database=} {--db-username=} {--db-password=}
        {--name=NextGenNews : Website name} {--url= : Website address, e.g. https://news.example.com}
        {--tagline=} {--locale=en : en | hi} {--timezone=Asia/Kolkata}
        {--environment=production : production | local}
        {--admin-name=} {--admin-email=} {--admin-password=}
        {--sample : Install the sample stories, polls and quiz}
        {--ai-key= : Claude API key (optional)}';

    protected $description = 'Install NextGenNews: write .env, create the tables, base data and your admin account';

    public function handle(InstallRunner $runner): int
    {
        if (Installer::isInstalled()) {
            $this->components->info('NextGenNews is already installed ('.(Installer::lockInfo()['version'] ?? 'unknown version').'). To apply a new version, run php artisan nextgennews:update (or Admin > System update).');

            return self::SUCCESS;
        }

        // 1. Requirements and folders
        $failed = collect([...Requirements::php(), ...Requirements::folders()])->filter(fn ($r) => ! $r['ok']);
        foreach ($failed as $r) {
            $r['required'] ? $this->components->error($r['name'].': '.$r['note']) : $this->components->warn($r['name'].' (optional): '.$r['note']);
        }
        if ($failed->contains('required', true)) {
            $this->components->error('Fix the items above, then run this command again.');

            return self::FAILURE;
        }
        $this->components->info('Server requirements: OK');

        // 2. Database
        $db = [
            'host' => $this->ask2('db-host', 'Database host', '127.0.0.1'),
            'port' => (int) $this->ask2('db-port', 'Database port', '3306'),
            'database' => $this->ask2('db-database', 'Database name (an empty database)'),
            'username' => $this->ask2('db-username', 'Database username', 'root'),
            'password' => $this->option('db-password') ?? ($this->input->isInteractive() ? password('Database password (empty for none)') : ''),
        ];
        $check = DatabaseCheck::test($db);
        if (! $check['ok']) {
            $this->components->error($check['message']);

            return self::FAILURE;
        }
        $this->components->info($check['message']);

        // 3. Site and admin
        $site = [
            'name' => $this->ask2('name', 'Website name', 'NextGenNews'),
            'url' => $this->ask2('url', 'Website address', config('app.url')),
            'tagline' => $this->option('tagline'),
            'locale' => $this->option('locale'),
            'timezone' => $this->option('timezone'),
            'env' => $this->option('environment'),
        ];
        $admin = [
            'name' => $this->ask2('admin-name', 'Your name'),
            'email' => $this->ask2('admin-email', 'Admin email'),
            'password' => $this->option('admin-password') ?? ($this->input->isInteractive() ? password('Admin password (10+ characters, letters and numbers)') : ''),
        ];
        $sample = $this->option('sample') || ($this->input->isInteractive() && confirm('Install sample data?', false));
        if ($this->input->isInteractive() && ! $this->input->hasParameterOption('--environment')) {
            $site['env'] = select('This installation is', ['production' => 'A live website', 'local' => 'For testing on this computer'], 'production');
        }

        $v = Validator::make([...$site, 'admin_name' => $admin['name'], 'admin_email' => $admin['email'], 'admin_password' => $admin['password']], [
            'name' => ['required', 'string', 'max:60'], 'url' => ['required', 'url'], 'locale' => ['in:en,hi'], 'timezone' => ['timezone'], 'env' => ['in:production,local'],
            'admin_name' => ['required', 'string', 'max:80'], 'admin_email' => ['required', 'email'], 'admin_password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->components->error($e);
            }

            return self::FAILURE;
        }

        // 4. Install
        $data = ['db' => $db, 'site' => $site, 'admin' => $admin, 'keys' => array_filter(['ai_key' => $this->option('ai-key')]), 'sample' => $sample, 'license' => null];
        foreach (InstallRunner::tasks($sample) as $task => $label) {
            $result = spin(fn () => $runner->run($task, $data), $label.'…');
            if (! $result['ok']) {
                $this->components->error($label.': '.$result['message']);
                $this->line('  Fix it and run the command again: finished steps are skipped and the site stays offline until the install completes.');

                return self::FAILURE;
            }
            $this->components->twoColumnDetail($label, '<fg=green>'.$result['message'].'</>');
        }

        $this->newLine();
        $this->components->info('Installed. Open '.rtrim($site['url'], '/').'/admin and sign in as '.$admin['email'].'.');
        $this->line('  Cron (every minute):  * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
        $this->line('  Queue worker:         php artisan queue:work --tries=3 --timeout=900');

        return self::SUCCESS;
    }

    /** The option if it was passed, else ask (interactive), else the default. */
    private function ask2(string $option, string $label, ?string $default = null): string
    {
        $value = $this->option($option) ?? $default;
        if ($this->input->hasParameterOption('--'.$option) || ! $this->input->isInteractive()) {
            return (string) $value;
        }

        return text($label, default: (string) $value, required: true);
    }
}
