<?php

namespace App\Support\Install;

use App\Services\Reels\Ffmpeg;

/** Server checks for the installer's Requirements and Permissions steps (and the CLI install). */
class Requirements
{
    /** @return list<array{name: string, ok: bool, required: bool, note: string}> */
    public static function php(): array
    {
        $min = config('installer.php');
        $rows = [[
            'name' => 'PHP '.$min.' or newer',
            'ok' => version_compare(PHP_VERSION, $min, '>='),
            'required' => true,
            'note' => 'This server has PHP '.PHP_VERSION.'.',
        ]];
        foreach (config('installer.extensions') as $ext) {
            $rows[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => true, 'note' => 'PHP extension'];
        }
        foreach (config('installer.recommended') as $ext => $why) {
            $rows[] = ['name' => $ext, 'ok' => extension_loaded($ext), 'required' => false, 'note' => $why];
        }
        $rows[] = ['name' => 'FFmpeg', 'ok' => self::ffmpeg(), 'required' => false, 'note' => 'Needed to make Reels. Everything else works without it.'];

        return $rows;
    }

    /** @return list<array{name: string, ok: bool, required: bool, note: string}> */
    public static function folders(): array
    {
        $rows = [];
        foreach (config('installer.writable') as $dir) {
            $path = base_path($dir);
            $rows[] = ['name' => $dir.'/', 'ok' => is_dir($path) && is_writable($path), 'required' => true, 'note' => is_dir($path) ? 'Must be writable' : 'Folder is missing'];
        }
        $env = config('installer.env_path');
        $rows[] = is_file($env)
            ? ['name' => '.env', 'ok' => is_writable($env), 'required' => true, 'note' => 'The installer saves your settings here']
            : ['name' => '.env (new file)', 'ok' => is_writable(dirname($env)), 'required' => true, 'note' => 'The project folder must be writable so .env can be created'];
        $rows[] = ['name' => 'public/', 'ok' => is_writable(public_path()), 'required' => false, 'note' => 'For the public/storage link. If it is not writable, create the link yourself (see INSTALL.md).'];

        return $rows;
    }

    /** @param list<array{ok: bool, required: bool}> $rows */
    public static function passes(array $rows): bool
    {
        return collect($rows)->every(fn ($r) => $r['ok'] || ! $r['required']);
    }

    public static function all(): bool
    {
        return self::passes(self::php()) && self::passes(self::folders());
    }

    private static function ffmpeg(): bool
    {
        try {
            return app(Ffmpeg::class)->available();
        } catch (\Throwable) {
            return false;
        }
    }
}
