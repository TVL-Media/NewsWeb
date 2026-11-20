<?php

namespace Database\Seeders;

use App\Models\AutoPublishSetting;
use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Base data every install needs: roles and permissions, categories, cities, pages, news sources
 * (example feeds are inactive) and the auto-publish rules. No people, no stories.
 * Run by the web installer and `php artisan nextgennews:install`.
 */
class InstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            TaxonomySeeder::class,
        ]);

        AutoPublishSetting::firstOrCreate([], [
            'sensitive_categories' => Category::where('is_sensitive', true)->pluck('slug')->all(),
            'sensitive_keywords' => [
                'election', 'poll body', 'arrested', 'court', 'verdict', 'riot', 'communal', 'temple', 'mosque',
                'killed', 'dead', 'death toll', 'disaster', 'earthquake', 'flood', 'child', 'minor', 'alleged', 'accused', 'cure',
            ],
            'confidence_threshold' => 0.85,
            'copy_threshold' => 0.15,
        ]);

        Cache::forget('nav.categories');
    }
}
