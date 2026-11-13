<?php

use App\Models\Category;
use App\Models\Reel;
use App\Models\Report;
use App\Models\Story;
use Database\Seeders\DatabaseSeeder;
use Laravel\Sanctum\Sanctum;

/*
| /api/v1/reels for the Android app (Sanctum tokens).
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['reels.enabled' => true]);
    $this->stories = Story::published()->orderByDesc('importance')->take(5)->get();
    $this->reels = $this->stories->map(fn ($s, $i) => makeReel($s, 'en', ['published_at' => now()->subMinutes(10 + $i)]));
});

it('lists reels with the fields the app needs', function () {
    $res = $this->getJson('/api/v1/reels')->assertOk();

    expect($res->json('data'))->toHaveCount(5)
        ->and($res->json('data.0'))->toHaveKeys([
            'id', 'video_720_url', 'video_480_url', 'thumbnail_url', 'duration', 'title', 'slug', 'language', 'other_language_reel_id',
            'page', 'story_id', 'story_slug', 'counts', 'is_liked', 'is_saved',
        ])
        ->and($res->json('data.0.page'))->toHaveKeys(['id', 'name', 'avatar', 'is_following'])
        ->and($res->json('data.0.counts'))->toHaveKeys(['likes', 'comments', 'shares', 'views'])
        ->and($res->json('meta.next_cursor'))->toBeNull();
});

it('pages through the feed with a cursor, without repeats', function () {
    $first = $this->getJson('/api/v1/reels?limit=2')->assertOk();
    $cursor = $first->json('meta.next_cursor');
    $second = $this->getJson('/api/v1/reels?limit=2&cursor='.$cursor)->assertOk();
    $third = $this->getJson('/api/v1/reels?limit=2&cursor='.$second->json('meta.next_cursor'))->assertOk();

    $ids = collect([...$first->json('data'), ...$second->json('data'), ...$third->json('data')])->pluck('id');
    expect($ids)->toHaveCount(5)->and($ids->unique())->toHaveCount(5)
        ->and($third->json('meta.next_cursor'))->toBeNull();

    $this->getJson('/api/v1/reels?cursor=not-a-cursor')->assertStatus(422);
});

it('filters by category and language', function () {
    $category = Category::find($this->stories[0]->category_id);
    $hi = makeReel($this->stories[1], 'hi');

    $byCategory = collect($this->getJson('/api/v1/reels?category='.$category->slug)->json('data'));
    expect($byCategory->pluck('id')->all())->toBe($this->reels->filter(fn ($r) => $r->category_id === $category->id)->pluck('id')->values()->all());

    $hindi = collect($this->getJson('/api/v1/reels?lang=hi')->json('data'));
    expect($hindi->firstWhere('story_id', $this->stories[1]->id)['id'])->toBe($hi->id);
});

it('puts reels from followed pages first for the signed-in reader', function () {
    $user = reader();
    $followedIds = $user->followedPages()->pluck('pages.id');
    $mine = makeReel(Story::published()->whereIn('page_id', $followedIds)->whereNotIn('id', $this->stories->pluck('id'))->first(), 'en', ['published_at' => now()->subDays(5)]);

    Sanctum::actingAs($user);

    $data = collect($this->getJson('/api/v1/reels')->json('data'));
    $firstOther = $data->search(fn ($r) => ! $followedIds->contains($r['page']['id']));

    // Every followed-page reel (including a 5-day-old one) comes before any other page's reel.
    expect($followedIds->contains($data[0]['page']['id']))->toBeTrue()
        ->and($data->search(fn ($r) => $r['id'] === $mine->id))->toBeLessThan($firstOther === false ? PHP_INT_MAX : $firstOther);
});

it('shows one reel, and 404s for reels that are not live', function () {
    $reel = $this->reels[0];
    $hi = makeReel($this->stories[0], 'hi');

    $this->getJson("/api/v1/reels/{$reel->id}")->assertOk()
        ->assertJsonPath('data.id', $reel->id)
        ->assertJsonPath('data.story_slug', $this->stories[0]->slug)
        ->assertJsonPath('data.other_language_reel_id', $hi->id);

    $reel->update(['status' => 'pending_approval']);
    $this->getJson("/api/v1/reels/{$reel->id}")->assertNotFound();
});

it('records views and shares from guests', function () {
    $reel = $this->reels[0];

    $this->postJson("/api/v1/reels/{$reel->id}/view", ['watched_seconds' => 21, 'completed' => true])->assertOk();
    $this->postJson("/api/v1/reels/{$reel->id}/share", ['channel' => 'whatsapp'])->assertOk()->assertJsonPath('shares', 1);

    expect($reel->fresh())->views_count->toBe(1)->shares_count->toBe(1);
});

it('needs a token to like, save, comment or report', function () {
    $reel = $this->reels[0];

    $this->postJson("/api/v1/reels/{$reel->id}/like")->assertUnauthorized();
    $this->postJson("/api/v1/reels/{$reel->id}/save")->assertUnauthorized();
    $this->postJson("/api/v1/reels/{$reel->id}/comments", ['body' => 'Nice'])->assertUnauthorized();
    $this->postJson("/api/v1/reels/{$reel->id}/report", ['reason' => 'spam'])->assertUnauthorized();
});

it('likes, saves, comments and reports with a Sanctum token', function () {
    $user = reader();
    $token = $user->createToken('android')->plainTextToken;
    $reel = $this->reels[0];
    $auth = ['Authorization' => 'Bearer '.$token];

    $this->postJson("/api/v1/reels/{$reel->id}/like", [], $auth)->assertJson(['liked' => true, 'likes' => 1]);
    $this->postJson("/api/v1/reels/{$reel->id}/save", [], $auth)->assertJson(['saved' => true]);
    $this->postJson("/api/v1/reels/{$reel->id}/comments", ['body' => 'Useful summary'], $auth)->assertCreated()->assertJsonPath('comment.body', 'Useful summary');
    $this->postJson("/api/v1/reels/{$reel->id}/report", ['reason' => 'misinformation'], $auth)->assertCreated();

    $this->getJson("/api/v1/reels/{$reel->id}", $auth)->assertJsonPath('data.is_liked', true)->assertJsonPath('data.is_saved', true)->assertJsonPath('data.counts.comments', 1);
    $this->getJson("/api/v1/reels/{$reel->id}/comments")->assertOk()->assertJsonPath('comments.0.body', 'Useful summary');
    expect(Report::where(['target_type' => 'reel', 'target_id' => $reel->id, 'reporter_id' => $user->id])->exists())->toBeTrue();

    $this->deleteJson("/api/v1/reels/{$reel->id}/like", [], $auth)->assertJson(['liked' => false, 'likes' => 0]);
});

it('applies admin restrictions to app readers', function () {
    $user = reader();
    $reel = $this->reels[0];

    $user->update(['status' => 'restricted', 'restricted_until' => now()->addDay()]);
    Sanctum::actingAs($user);
    $this->postJson("/api/v1/reels/{$reel->id}/like")->assertForbidden()->assertJsonPath('account_status', 'restricted');
    $this->postJson("/api/v1/reels/{$reel->id}/view", ['watched_seconds' => 5])->assertOk(); // watching still counts
    $this->getJson('/api/v1/reels')->assertOk();

    $user->update(['status' => 'suspended']);
    $this->getJson('/api/v1/reels')->assertForbidden()->assertJsonPath('account_status', 'suspended');
});

it('404s every reels endpoint when REELS_ENABLED is false', function () {
    config(['reels.enabled' => false]);

    $this->getJson('/api/v1/reels')->assertNotFound();
    $this->getJson('/api/v1/reels/'.$this->reels[0]->id)->assertNotFound();
});

it('does not expose the scaffold /api/user route', function () {
    Sanctum::actingAs(reader());

    $this->getJson('/api/user')->assertNotFound();
});
