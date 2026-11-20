<?php

namespace App\Support\Install;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Is the app installed? The lock file (storage/installed) says so. A site that was set up before the
 * installer existed (tables + at least one admin) is recognised and locked automatically, so it never
 * sees /install. While an install is running, a marker file stops that check from locking a
 * half-finished install.
 */
class Installer
{
    private static ?bool $installed = null;

    public static function isInstalled(): bool
    {
        if (config('installer.assume_installed')) {
            return true;
        }
        if (self::$installed) {
            return true;
        }
        if (is_file(self::lockPath())) {
            return self::$installed = true;
        }
        if (! is_file(self::installingPath()) && self::existingInstallFound()) {
            self::lock(['detected' => true]);

            return self::$installed = true;
        }

        return false;
    }

    /** Forget the remembered answer (tests, and after the lock is written). */
    public static function refresh(): void
    {
        self::$installed = null;
    }

    public static function lockPath(): string
    {
        return config('installer.lock_path');
    }

    public static function installingPath(): string
    {
        return dirname(self::lockPath()).DIRECTORY_SEPARATOR.'installing';
    }

    /** @return array{version?: string, installed_at?: string, detected?: bool}|null */
    public static function lockInfo(): ?array
    {
        $raw = @file_get_contents(self::lockPath());

        return $raw === false ? null : (json_decode($raw, true) ?: []);
    }

    public static function lock(array $extra = []): void
    {
        @file_put_contents(self::lockPath(), json_encode([
            'version' => config('app-version.version'),
            'installed_at' => now()->toIso8601String(),
            ...$extra,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @unlink(self::installingPath());
        self::$installed = true;
    }

    /** After an update: record the new version, keep the original install date. */
    public static function setVersion(string $version): void
    {
        $info = self::lockInfo() ?? [];
        $info['version'] = $version;
        $info['updated_at'] = now()->toIso8601String();
        $info['installed_at'] ??= $info['updated_at'];
        @file_put_contents(self::lockPath(), json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public static function markInstalling(): void
    {
        @file_put_contents(self::installingPath(), now()->toIso8601String());
    }

    /** Tables and an admin already exist in the configured database (a site installed by hand). */
    private static function existingInstallFound(): bool
    {
        if (! is_file(config('installer.env_path'))) {
            return false; // fresh upload: nothing configured yet
        }
        try {
            return Schema::hasTable('migrations') && Schema::hasTable('admins') && DB::table('admins')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Before installation: make the installer itself work on a bare server. Files for sessions and
     * cache (no database or Redis yet), and an app key (written to .env, created from .env.example).
     */
    public static function prepareRuntime(): void
    {
        config([
            'session.driver' => 'file',
            'cache.default' => 'file',
            'queue.default' => 'sync',
        ]);

        if (filled(config('app.key'))) {
            return;
        }
        $key = 'base64:'.base64_encode(random_bytes(32));
        $env = config('installer.env_path');
        try {
            if (! is_file($env) && is_file(base_path('.env.example'))) {
                @copy(base_path('.env.example'), $env);
                // This .env is the installer's own: its example database settings may point at some other
                // site's database, which must never make this copy count as "already installed".
                self::markInstalling();
            }
            if (is_file($env) && is_writable($env)) {
                EnvFile::set($env, ['APP_KEY' => $key]);
            } else {
                // No writable .env yet (the Permissions step says so): keep one key for this server.
                $file = storage_path('installer.key');
                $key = is_file($file) ? trim((string) file_get_contents($file)) : $key;
                @file_put_contents($file, $key);
            }
        } catch (\Throwable) {
        }
        config(['app.key' => $key]);
    }

    /** A purchase code looks like a UUID. */
    public static function looksLikePurchaseCode(string $code): bool
    {
        return Str::isUuid(trim($code));
    }
}
