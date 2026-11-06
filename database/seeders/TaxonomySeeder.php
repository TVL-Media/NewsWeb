<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\City;
use App\Models\FeedSource;
use App\Models\Page;
use App\Models\Source;
use Database\Seeders\Support\DemoMedia;
use Illuminate\Database\Seeder;

/** 8 categories, 6 cities, sources, demo feed sources and 10 pages. */
class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['india', 'India', 'भारत', false],
            ['world', 'World', 'दुनिया', false],
            ['business', 'Business', 'बिज़नेस', false],
            ['cricket', 'Cricket', 'क्रिकेट', false],
            ['tech', 'Tech', 'टेक', false],
            ['health', 'Health', 'सेहत', true],
            ['good-news', 'Good News', 'अच्छी ख़बर', false],
            ['entertainment', 'Entertainment', 'मनोरंजन', false],
        ];
        foreach ($categories as $i => [$slug, $en, $hi, $sensitive]) {
            Category::create([
                'slug' => $slug, 'name_en' => $en, 'name_hi' => $hi, 'sort_order' => $i + 1,
                'is_sensitive' => $sensitive,
                'image_path' => DemoMedia::put("category-{$slug}", 'categories'),
            ]);
        }

        foreach ([
            ['Lucknow', 'लखनऊ', 'Uttar Pradesh'],
            ['Delhi', 'दिल्ली', 'Delhi'],
            ['Mumbai', 'मुंबई', 'Maharashtra'],
            ['Jaipur', 'जयपुर', 'Rajasthan'],
            ['Patna', 'पटना', 'Bihar'],
            ['Bhopal', 'भोपाल', 'Madhya Pradesh'],
        ] as [$en, $hi, $state]) {
            City::create(['name_en' => $en, 'name_hi' => $hi, 'state' => $state]);
        }

        foreach ([
            ['PTI', 'https://www.ptinews.com'],
            ['ANI', 'https://www.aninews.in'],
            ['IANS', 'https://ians.in'],
            ['Reuters', 'https://www.reuters.com'],
            ['NextGenNews Desk', null],
        ] as [$name, $url]) {
            Source::create(['name' => $name, 'url' => $url]);
        }

        // Inactive examples so the Feeds screen isn't empty; real feeds are added in /admin.
        FeedSource::create(['name' => 'Agency wire (example)', 'url' => 'https://example.com/agency/feed.xml', 'type' => 'agency', 'trusted' => true, 'active' => false]);
        FeedSource::create(['name' => 'Publisher RSS (example)', 'url' => 'https://example.com/rss.xml', 'type' => 'rss', 'trusted' => false, 'active' => false]);

        $cat = Category::pluck('id', 'slug');
        $city = City::pluck('id', 'name_en');

        $pages = [
            ['india-now', 'India Now', 'भारत अभी', 'topic', 'india', null, 'NATIONAL DESK', 'Railways, weather, education and the stories shaping the country, explained in 60 words.'],
            ['world-brief', 'World Brief', 'दुनिया एक नज़र में', 'topic', 'world', null, 'WORLD DESK', 'What happened around the world today and why it matters for India.'],
            ['markets', 'Markets', 'बाज़ार', 'topic', 'business', null, 'BUSINESS DESK', 'Sensex, Nifty, RBI, startups and your money, without the jargon.'],
            ['cricket', 'Cricket', 'क्रिकेट', 'topic', 'cricket', null, 'SPORTS DESK', 'Match reports, live scores, explainers and polls from India and around the world.'],
            ['tech-today', 'Tech Today', 'टेक टुडे', 'topic', 'tech', null, 'TECH DESK', 'Space, gadgets, AI and the internet in India.'],
            ['health-watch', 'Health Watch', 'हेल्थ वॉच', 'topic', 'health', null, 'HEALTH DESK', 'Checked health news: hospitals, vaccines, air quality and wellbeing.'],
            ['screen-stage', 'Screen & Stage', 'पर्दा और मंच', 'topic', 'entertainment', null, 'ENTERTAINMENT DESK', 'Films, box office, festivals and streaming.'],
            ['good-news-daily', 'Good News Daily', 'रोज़ की अच्छी ख़बर', 'show', 'good-news', null, 'GOOD NEWS', 'One uplifting story every day from across India.'],
            ['lucknow', 'Lucknow', 'लखनऊ', 'city', 'india', 'Lucknow', 'CITY DESK', 'Metro, roads, schools, festivals and civic news from Lucknow.'],
            ['delhi', 'Delhi', 'दिल्ली', 'city', 'india', 'Delhi', 'CITY DESK', 'Air quality, Metro, traffic and civic news from the capital.'],
        ];
        foreach ($pages as [$slug, $name, $nameHi, $type, $category, $cityName, $desk, $description]) {
            Page::create([
                'slug' => $slug, 'name' => $name, 'name_hi' => $nameHi, 'type' => $type,
                'category_id' => $cat[$category], 'city_id' => $cityName ? $city[$cityName] : null,
                'desk_label' => $desk, 'description' => $description, 'verified' => true,
                'avatar_path' => DemoMedia::put("avatar-{$slug}", 'pages', 300, 300),
                'cover_path' => DemoMedia::put("cover-{$slug}", 'pages', 1600, 640),
            ]);
        }

        // Cricket: "Upcoming" tiles (pinned result is set in StorySeeder, once stories exist).
        Page::where('slug', 'cricket')->update(['upcoming' => json_encode([
            ['date' => now()->addDays(3)->toDateString(), 'title' => 'India vs New Zealand, 1st ODI', 'subtitle' => 'Ahmedabad · 1:30 PM'],
            ['date' => now()->addDays(6)->toDateString(), 'title' => 'India vs New Zealand, 2nd ODI', 'subtitle' => 'Rajkot · 1:30 PM'],
            ['date' => now()->addDays(9)->toDateString(), 'title' => 'Ranji Trophy: UP vs Bengal', 'subtitle' => 'Lucknow · 9:30 AM'],
        ])]);
    }
}
