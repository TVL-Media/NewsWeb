<?php

use App\Models\Page;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->top = Story::published()->with('versions')->where('slug', 'india-beat-new-zealand-by-seven-wickets-to-clinch-test-series-2-0')->first();
});

describe('home', function () {
    it('shows the hero, Top 5 trending, quiz, pages to follow and a poll', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee($this->top->version('en')->headline)
            ->assertSee('Top 5')
            ->assertSee('Trending right now')
            ->assertSee(route('story.show', \App\Support\Rails::trending()[0]), false)
            ->assertSee('Daily quiz')
            ->assertSee('Pages to follow')
            ->assertSee('Who should open the batting in the ODI series?')
            ->assertSee('Load more');
    });

    it('shows category circles to guests and followed-page story circles to readers', function () {
        $this->get('/')->assertSee('?category=india', false)->assertDontSee('open-stories', false);

        $reader = User::where('phone', '9876543210')->first();
        $this->actingAs($reader)->get('/')
            ->assertSee('Good ')                     // Good Morning/Afternoon/Evening, Riya
            ->assertSee('Riya')
            ->assertSee('open-stories', false)       // circles open the viewer
            ->assertSee('Swipe up to read')
            ->assertSee('keeps your 12-day streak');
    });

    it('shows topic circles to signed-in readers who follow no pages with live stories', function () {
        $user = User::factory()->create();
        $user->categories()->attach(\App\Models\Category::whereIn('slug', ['business', 'tech'])->pluck('id'));

        $this->actingAs($user)->get('/')
            ->assertSee('?category=business', false)
            ->assertSee('?category=tech', false)
            ->assertDontSee('open-stories', false);
    });

    it('still shows topic circles once every followed page story has expired', function () {
        \App\Models\PageStory::query()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs(User::where('phone', '9876543210')->first())->get('/')
            ->assertDontSee('open-stories', false)
            ->assertSee('?category=cricket', false);
    });

    it('gives a page a fresh 24-hour circle when one of its stories is published', function () {
        $story = Story::published()->whereNotNull('page_id')->whereNotNull('photo_path')->first();
        \App\Models\PageStory::query()->delete();

        app(\App\Services\Newsroom\StoryPublisher::class)->publish($story, 'manual');

        $circle = \App\Models\PageStory::where('story_id', $story->id)->first();
        expect($circle->page_id)->toBe($story->page_id)
            ->and($circle->image_path)->toBe($story->photo_path)
            ->and($circle->expires_at->isFuture())->toBeTrue();
    });

    it('never deletes a story photo when its circle expires', function () {
        $story = Story::published()->whereNotNull('photo_path')->first();
        \App\Models\PageStory::create(['page_id' => $story->page_id, 'story_id' => $story->id, 'image_path' => $story->photo_path, 'expires_at' => now()->subMinute()]);

        \App\Jobs\ExpirePageStories::dispatchSync();

        Storage::disk('public')->assertExists($story->photo_path);
    });

    it('returns the next page of Latest News as a fragment for Load more', function () {
        $response = $this->get('/?page=2&fragment=1')->assertOk();

        expect($response->getContent())->not->toContain('<html')
            ->and(substr_count($response->getContent(), '<article'))->toBeGreaterThan(0);
    });
});

describe('story page', function () {
    it('renders all three tabs, why it matters, comments and the rail', function () {
        $en = $this->top->version('en');

        $this->get(route('story.show', $this->top))
            ->assertOk()
            ->assertSee('Home')->assertSee('Sports')->assertSee('Cricket')   // breadcrumb
            ->assertSee($en->key_points[0])
            ->assertSee($en->simple_text)
            ->assertSee($en->paragraphs()[0])
            ->assertSee($en->why_it_matters)
            ->assertSee('What a finish to the series')
            ->assertSee('Log in to comment')
            ->assertSee('Related stories')
            ->assertSee('AD · 300×250')
            ->assertSee('Share to WhatsApp');
    });

    it('includes both languages so the हिंदी switch works without reloading', function () {
        $this->get(route('story.show', $this->top))
            ->assertSee('Read in')
            ->assertSee($this->top->version('hi')->headline)
            ->assertSee($this->top->version('hi')->key_points[0]);
    });

    it('has SEO tags and NewsArticle JSON-LD', function () {
        $this->get(route('story.show', $this->top))
            ->assertSee('<link rel="canonical" href="'.route('story.show', $this->top).'">', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('<meta name="description" content="'.e($this->top->version('en')->card_text).'">', false);
    });

    it('generates Listen audio with the fake TTS and links it when audio is on', function () {
        config(['services.audio.enabled' => true]);
        $version = $this->top->version('en');

        expect($version->audio_path)->toEndWith('.wav')
            ->and($version->audio_seconds)->toBeGreaterThan(5);
        Storage::disk('public')->assertExists($version->audio_path);

        $this->get(route('story.show', $this->top))->assertSee(basename($version->audio_path), false);
    });

    it('hides every Listen button and the audio player when audio is off (default)', function () {
        $this->get('/')->assertOk()->assertDontSee('ngn-listen', false)->assertDontSee('<audio', false);
        $this->get(route('story.show', $this->top))->assertOk()->assertDontSee('ngn-listen', false)->assertDontSee('Listen ·');
        $this->actingAs(User::where('phone', '9876543210')->first())->get('/for-you')->assertOk()->assertDontSee('ngn-listen', false);
    });

    it('hides stories that are not published', function () {
        $this->top->update(['status' => 'pending_approval']);

        $this->get(route('story.show', $this->top))->assertNotFound();
    });
});

describe('topic page', function () {
    it('shows the banner, result strip, top story, more stories and the rail', function () {
        $this->get('/page/cricket')
            ->assertOk()
            ->assertSee('SPORTS DESK')
            ->assertSee('RESULT')
            ->assertSee('Match report')
            ->assertSee('Top story')
            ->assertSee('More stories')
            ->assertSee('Upcoming')
            ->assertSee('Related topics');
    });

    it('filters explainers and lists the page polls', function () {
        $this->get('/page/cricket?tab=explainers')->assertOk();
        $this->get('/page/cricket?tab=polls')->assertOk()->assertSee('Who should open the batting in the ODI series?');
    });
});

it('shows 8 category tiles, trending and pages on Explore', function () {
    $response = $this->get('/explore')->assertOk()
        ->assertSee('Discover news by topics')
        ->assertSee('Trending Now')
        ->assertSee('Pages to follow');

    foreach (['India', 'World', 'Business', 'Cricket', 'Tech', 'Health', 'Good News', 'Entertainment'] as $name) {
        $response->assertSee($name);
    }
});

describe('search', function () {
    it('finds stories by headline and pages by name', function () {
        $this->get('/search?q=Metro')->assertOk()->assertSee('Lucknow Metro');
        $this->get('/search?q=Cricket&type=pages')->assertOk()->assertSee('Match reports')->assertDontSee('Stories</h2>', false);
    });

    it('finds stories by Hindi words and shows them in the reader\'s language', function () {
        $word = explode(' ', $this->top->version('hi')->headline)[2];

        $this->get('/search?q='.urlencode($word))->assertOk()->assertSee($this->top->version('en')->headline);
        $this->withUnencryptedCookie('lang', 'hi')->get('/search?q='.urlencode($word))->assertSee($this->top->version('hi')->headline);
    });

    it('filters by category and shows a no-results state', function () {
        $this->get('/search?q=Metro&category=cricket')->assertSee('No results for');
        $this->get('/search?q=zzqqxx')->assertSee('No results for');
    });
});

it('serves the static pages, with grievance officer contact details', function () {
    foreach (['/about', '/privacy', '/terms', '/community-rules'] as $url) {
        $this->get($url)->assertOk();
    }

    $this->get('/grievance-officer')->assertOk()->assertSee('grievance@nextgennews.in');
});
