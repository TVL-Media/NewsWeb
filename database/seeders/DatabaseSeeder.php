<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Development and tests: base data + demo staff accounts + sample content (`php artisan db:seed`).
 * A real install uses the installer instead, which runs InstallSeeder and, if ticked, SampleDataSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Fresh demo media on every seed.
        $disk = Storage::disk(config('filesystems.media'));
        foreach (['categories', 'pages', 'stories', 'page-stories', 'audio', 'reels'] as $dir) {
            $disk->deleteDirectory($dir);
        }

        $this->call([
            InstallSeeder::class,
            DemoAccountsSeeder::class,
            SampleDataSeeder::class,
        ]);
    }
}
