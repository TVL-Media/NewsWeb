<?php

namespace Database\Seeders;

use App\Models\AiCheck;
use App\Models\Category;
use App\Models\City;
use App\Models\Page;
use App\Models\Source;
use App\Models\Story;
use App\Services\StoryAudio;
use Database\Seeders\Support\DemoMedia;
use Illuminate\Database\Seeder;

/** 30 published stories in English and Hindi, from database/seeders/data/stories_*.php. */
class StorySeeder extends Seeder
{
    public function run(): void
    {
        $stories = array_merge(
            require __DIR__.'/data/stories_1.php',
            require __DIR__.'/data/stories_2.php',
            require __DIR__.'/data/stories_3.php',
        );

        $categories = Category::pluck('id', 'slug');
        $pages = Page::pluck('id', 'slug');
        $cities = City::all()->keyBy(fn ($c) => strtolower($c->name_en));
        $sources = Source::pluck('id', 'name');
        $audio = app(StoryAudio::class);

        foreach ($stories as $i => $data) {
            $story = Story::create([
                'slug' => $data['slug'],
                'category_id' => $categories[$data['category']],
                'page_id' => $pages[$data['page']],
                'city_id' => $data['city'] ? $cities[$data['city']]->id : null,
                'source_id' => $sources[$data['source']] ?? null,
                'source_url' => 'https://example.com/source/'.$data['slug'],
                'status' => 'published',
                'publish_mode' => $i % 3 === 0 ? 'manual' : 'auto',
                'is_breaking' => $data['is_breaking'],
                'importance' => $data['importance'],
                'read_minutes' => $data['read_minutes'],
                'photo_path' => DemoMedia::put(pathinfo($data['photo'], PATHINFO_FILENAME), 'stories'),
                'photo_credit' => 'Picsum / Unsplash (demo)',
                'likes_count' => 40 + ($i * 37) % 900,
                'comments_count' => 0,
                'shares_count' => 10 + ($i * 13) % 200,
                'published_at' => now()->subMinutes($data['minutes_ago']),
            ]);

            foreach (['en', 'hi'] as $lang) {
                $v = $data[$lang];
                $version = $story->versions()->create([
                    'language' => $lang,
                    'headline' => $v['headline'],
                    'card_text' => $v['card_text'],
                    'key_points' => $v['key_points'],
                    'why_it_matters' => $v['why_it_matters'],
                    'simple_text' => $v['simple_text'],
                    'full_text' => $v['full_text'],
                    'ai_assisted' => true,
                ]);
                $audio->generate($version); // FakeTts locally (TTS_DRIVER=fake)
            }

            AiCheck::create([
                'story_id' => $story->id,
                'risk_level' => $data['category'] === 'health' ? 'sensitive' : 'low',
                'sensitive_reasons' => $data['category'] === 'health' ? ['health claims'] : [],
                'confidence' => 0.9 + ($i % 9) / 100,
                'fact_mismatches' => [],
                'copy_score' => 0.03 + ($i % 7) / 100,
                'decision' => $i % 3 === 0 || $data['category'] === 'health' ? 'approval' : 'auto',
            ]);
        }

        // Cricket page: black "RESULT" strip pinned to the top story's match report.
        $top = Story::where('slug', $stories[0]['slug'])->first();
        Page::where('slug', 'cricket')->update([
            'pinned_label' => 'RESULT',
            'pinned_text' => 'IND 312 & 148/3 beat NZ 265 & 194 by 7 wickets · India win series 2–0',
            'pinned_story_id' => $top?->id,
        ]);
    }
}
