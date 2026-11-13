<?php

use App\Models\Comment;
use App\Models\Reel;
use App\Models\ReelView;
use App\Models\Save;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['reels.enabled' => true]);
});

it('shows Reels in the desktop header and as the 5th mobile nav item', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect(substr_count($html, 'href="'.route('reels').'"'))->toBeGreaterThanOrEqual(2)
        ->and($html)->toContain('repeat(5, minmax(0, 1fr))');
});

it('hides every reels link and page when REELS_ENABLED is false', function () {
    $story = Story::published()->first();
    $reel = makeReel($story);
    config(['reels.enabled' => false]);

    $this->get('/reels')->assertNotFound();
    $this->get('/reels/'.$reel->slug)->assertNotFound();
    $this->postJson("/reels/{$reel->id}/view", ['watched_seconds' => 3])->assertNotFound();

    $this->get('/')->assertOk()->assertDontSee(route('reels'))->assertDontSee('repeat(5, minmax(0, 1fr))', false);
    $this->get('/explore')->assertOk()->assertDontSee(route('reels'));
    $this->get(route('story.show', $story))->assertOk()->assertDontSee('Watch reel');
    $this->get(route('page.show', [$story->page, 'tab' => 'reels']))->assertOk()->assertDontSee('No reels on this page yet.');
});

it('renders the reels feed with published reels only', function () {
    $stories = Story::published()->orderByDesc('importance')->take(3)->get();
    $live = makeReel($stories[0]);
    makeReel($stories[1], 'en', ['status' => 'pending_approval', 'published_at' => null]);
    makeReel($stories[2], 'en', ['status' => 'failed']);

    $this->get('/reels')->assertOk()
        ->assertSee($live->title)
        ->assertDontSee($stories[1]->version('en')->headline.'","language', false)
        ->assertSee('reelFeed(', false);

    $json = $this->getJson('/reels/feed')->assertOk()->json('reels');
    expect(collect($json)->pluck('id')->all())->toBe([$live->id]);
});

it('serves the reel JSON shape the player and the app use', function () {
    $story = Story::published()->first();
    $en = makeReel($story);
    $hi = makeReel($story, 'hi');

    $reel = $this->getJson("/reels/{$en->id}/data")->assertOk()->json('reel');

    expect($reel)->toHaveKeys(['video_720_url', 'video_480_url', 'thumbnail_url', 'duration', 'title', 'slug', 'language',
        'other_language_reel_id', 'page', 'story_id', 'story_slug', 'counts', 'is_liked', 'is_saved'])
        ->and($reel['other_language_reel_id'])->toBe($hi->id)
        ->and($reel['page'])->toHaveKeys(['id', 'name', 'avatar', 'is_following'])
        ->and($reel['story_slug'])->toBe($story->slug)
        ->and($reel['duration'])->toBe(28);
});

it('shows one reel per story in the reader\'s language, falling back to the other language', function () {
    [$a, $b] = Story::published()->orderByDesc('importance')->take(2)->get();
    makeReel($a, 'en');
    makeReel($a, 'hi');
    makeReel($b, 'hi'); // no English reel for $b

    $reels = collect($this->getJson('/reels/feed')->json('reels'));

    expect($reels->where('story_id', $a->id)->pluck('language')->all())->toBe(['en'])
        ->and($reels->where('story_id', $b->id)->pluck('language')->all())->toBe(['hi']);

    // JSON test requests only carry cookies with withCredentials().
    $hindi = collect($this->withCredentials()->withUnencryptedCookie('lang', 'hi')->getJson('/reels/feed')->json('reels'));
    expect($hindi->where('story_id', $a->id)->pluck('language')->all())->toBe(['hi']);
});

it('puts reels from followed pages first for signed-in readers', function () {
    $user = reader();
    $followedIds = $user->followedPages()->pluck('pages.id');
    $other = Story::published()->whereNotIn('page_id', $followedIds)->orderByDesc('published_at')->first();
    $followed = Story::published()->whereIn('page_id', $followedIds)->orderBy('published_at')->first();

    makeReel($other, 'en', ['published_at' => now()->subMinutes(5), 'views_count' => 999]);
    $mine = makeReel($followed, 'en', ['published_at' => now()->subDays(3)]);

    expect($this->actingAs($user)->getJson('/reels/feed')->json('reels.0.id'))->toBe($mine->id);
});

it('opens /reels/{slug} at that reel with SEO, Open Graph video and VideoObject JSON-LD', function () {
    $stories = Story::published()->orderByDesc('importance')->take(2)->get();
    makeReel($stories[0]);
    $reel = makeReel($stories[1]);

    $html = $this->get('/reels/'.$reel->slug)->assertOk()
        ->assertSee('<title>'.e($reel->title).' · NextGenNews</title>', false)
        ->assertSee('<meta property="og:type" content="video.other">', false)
        ->assertSee('<meta property="og:video" content="'.$reel->videoUrl('720').'">', false)
        ->assertSee('"@type":"VideoObject"', false)
        ->assertSee('"duration":"PT28S"', false)
        ->getContent();

    // The feed starts at this reel: its title comes first in the player's data.
    $data = substr($html, strpos($html, 'reelFeed('));
    expect(strpos($data, $reel->slug))->toBeLessThan(strpos($data, $stories[0]->slug));
});

it('404s for a reel that is not published', function () {
    $reel = makeReel(Story::published()->first(), 'en', ['status' => 'pending_approval', 'published_at' => null]);

    $this->get('/reels/'.$reel->slug)->assertNotFound();
});

it('records views from guests and readers', function () {
    $reel = makeReel(Story::published()->first());

    $this->postJson("/reels/{$reel->id}/view", ['watched_seconds' => 12, 'completed' => true])->assertOk();
    $this->actingAs(reader())->postJson("/reels/{$reel->id}/view", ['watched_seconds' => 4])->assertOk();

    expect(ReelView::count())->toBe(2)
        ->and(ReelView::whereNull('user_id')->first()->completed)->toBeTrue()
        ->and($reel->fresh()->views_count)->toBe(2);

    $this->postJson("/reels/{$reel->id}/view", ['watched_seconds' => -5])->assertUnprocessable();
});

it('asks guests to log in to like, save or comment, but lets them share', function () {
    $reel = makeReel(Story::published()->first());

    $this->postJson("/reels/{$reel->id}/like")->assertUnauthorized();
    $this->postJson("/reels/{$reel->id}/save")->assertUnauthorized();
    $this->postJson("/reels/{$reel->id}/comments", ['body' => 'Hello'])->assertUnauthorized();
    $this->postJson("/reels/{$reel->id}/share", ['channel' => 'whatsapp'])->assertOk()->assertJsonPath('shares', 1);
});

it('likes, saves and shares a reel for signed-in readers', function () {
    $reel = makeReel(Story::published()->first());
    $this->actingAs(reader());

    $this->postJson("/reels/{$reel->id}/like")->assertJson(['liked' => true, 'likes' => 1]);
    $this->postJson("/reels/{$reel->id}/like")->assertJson(['likes' => 1]); // idempotent
    $this->deleteJson("/reels/{$reel->id}/like")->assertJson(['liked' => false, 'likes' => 0]);

    $this->postJson("/reels/{$reel->id}/save")->assertJson(['saved' => true]);
    expect(Save::where('reel_id', $reel->id)->count())->toBe(1);
    $this->getJson("/reels/{$reel->id}/data")->assertJsonPath('reel.is_saved', true);
    $this->deleteJson("/reels/{$reel->id}/save")->assertJson(['saved' => false]);

    $this->postJson("/reels/{$reel->id}/share", ['channel' => 'link'])->assertJsonPath('url', route('reels.show', $reel));
});

it('posts, replies to and lists comments on a reel', function () {
    $reel = makeReel(Story::published()->first());
    $this->actingAs(reader());

    $first = $this->postJson("/reels/{$reel->id}/comments", ['body' => 'Great reel'])->assertCreated()->json('comment');
    $this->postJson("/reels/{$reel->id}/comments", ['body' => 'Agreed', 'parent_id' => $first['id']])->assertCreated();

    $list = $this->getJson("/reels/{$reel->id}/comments")->assertOk()->json('comments');

    expect($list)->toHaveCount(1)
        ->and($list[0]['body'])->toBe('Great reel')
        ->and($list[0]['replies'][0]['body'])->toBe('Agreed')
        ->and($reel->fresh()->comments_count)->toBe(2)
        ->and(Comment::where('reel_id', $reel->id)->whereNull('story_id')->count())->toBe(2);
});

it('lets readers report a reel', function () {
    $reel = makeReel(Story::published()->first());

    $this->actingAs(reader())->postJson('/reports', ['target_type' => 'reel', 'target_id' => $reel->id, 'reason' => 'misinformation'])
        ->assertSuccessful();
});

it('shows a Reels row on Home with 9:16 cards that open the feed at that reel', function () {
    $reel = makeReel(Story::published()->orderByDesc('importance')->first());

    $this->get('/')->assertOk()
        ->assertSee('reels-row-title', false)
        ->assertSee(route('reels.show', $reel), false)
        ->assertSee('0:28')
        ->assertSee('aspect-[9/16]', false);
});

it('shows a Watch reel pill on the story page when the story has a reel', function () {
    $story = Story::published()->first();
    $other = Story::published()->whereKeyNot($story->id)->first();
    $reel = makeReel($story);

    $this->get(route('story.show', $story))->assertOk()->assertSee('Watch reel')->assertSee(route('reels.show', $reel), false);
    $this->get(route('story.show', $other))->assertOk()->assertDontSee('Watch reel');
});

it('has a Reels tab on topic pages that shows that topic\'s reels', function () {
    $story = Story::published()->whereNotNull('page_id')->first();
    $mine = makeReel($story);
    $elsewhere = makeReel(Story::published()->where('page_id', '!=', $story->page_id)->first());

    $this->get(route('page.show', $story->page))->assertOk()->assertSee('?tab=reels', false);

    $this->get(route('page.show', [$story->page, 'tab' => 'reels']))->assertOk()
        ->assertSee($mine->title)
        ->assertDontSee($elsewhere->title)
        ->assertSee('topic='.$story->page->slug, false);

    expect(collect($this->getJson('/reels/feed?topic='.$story->page->slug)->json('reels'))->pluck('id')->all())->toBe([$mine->id]);
});

it('links the Explore Reels tile to the real feed', function () {
    $this->get('/explore')->assertOk()
        ->assertSee('href="'.route('reels').'" class="hero-card', false)
        ->assertDontSee('Short video news is coming in a later phase.');
});
