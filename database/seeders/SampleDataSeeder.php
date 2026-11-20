<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Optional sample content ("Install sample data" in the installer): 30 stories in English and Hindi,
 * demo readers, comments, polls, today's quiz and Morning 5, page story circles and sample reels.
 * Photos are generated placeholders unless storage/app/demo/ holds images (see CREDITS.md).
 * Needs InstallSeeder first. Remove it any time from the admin panel; nothing else depends on it.
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            StorySeeder::class,
            DemoActivitySeeder::class,
            ReelSeeder::class,
        ]);

        Cache::forget('nav.categories');
    }
}
