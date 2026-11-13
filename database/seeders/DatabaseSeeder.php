<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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
            TaxonomySeeder::class,
            StorySeeder::class,
            DemoActivitySeeder::class,
            ReelSeeder::class,
        ]);

        Cache::forget('nav.categories');
    }
}
