<?php

use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Save;
use App\Models\Story;
use App\Models\User;
use App\Support\LiveVersion;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function otpLogin($test, string $phone): void
{
    config(['services.sms.dev_code' => '123456']);
    $test->postJson('/auth/otp/send', ['phone' => $phone])->assertOk();
    $test->postJson('/auth/otp/verify', ['phone' => $phone, 'code' => '123456'])->assertOk();
}

it('keeps a reader\'s name, topics, saves, likes and comments after logging out and back in', function () {
    $story = Story::published()->first();
    $topics = Category::take(2)->pluck('id')->all();

    otpLogin($this, '9123456780');
    $this->post('/onboarding', ['language' => 'en', 'categories' => $topics])->assertRedirect();   // as a new reader would
    $this->patchJson('/account', ['name' => 'Anjali'])->assertSuccessful();
    $this->postJson("/stories/{$story->id}/save")->assertOk();
    $this->postJson("/stories/{$story->id}/like")->assertOk();
    $this->postJson("/stories/{$story->id}/comments", ['body' => 'Keeping this one'])->assertCreated();

    $this->post('/logout')->assertRedirect();
    $this->assertGuest();

    otpLogin($this, '9123456780');
    $user = User::where('phone', '9123456780')->sole();

    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Anjali')
        ->and($user->categories()->pluck('categories.id')->sort()->values()->all())->toBe(collect($topics)->sort()->values()->all())
        ->and(Save::where(['user_id' => $user->id, 'story_id' => $story->id])->exists())->toBeTrue()
        ->and(Like::where(['user_id' => $user->id, 'likeable_id' => $story->id, 'likeable_type' => Story::class])->exists())->toBeTrue()
        ->and(Comment::where(['user_id' => $user->id, 'body' => 'Keeping this one'])->exists())->toBeTrue();

    $this->get('/account/saved')->assertOk()->assertSee($story->version('en')->headline);
});

it('builds the share title on story pages even with audio off (share button works)', function () {
    config(['services.audio.enabled' => false]);
    $story = Story::published()->first();

    $this->get(route('story.show', $story))->assertOk()
        ->assertSee('headlines:', false)
        ->assertSee("\$dispatch('ngn-share'", false);
});

it('serves a live fingerprint that changes when a story is published or edited', function () {
    $before = $this->getJson('/live')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('v');

    $this->travel(2)->minutes();
    Story::published()->first()->touch();
    Cache::forget('live.version');

    expect($this->getJson('/live')->json('v'))->not->toBe($before);
    $this->get('/')->assertSee('live: "'.LiveVersion::current().'"', false)->assertSee('autoRefresh: true', false);
});

it('does not auto-refresh the reels player', function () {
    config(['reels.enabled' => true]);

    $this->get('/reels')->assertOk()->assertSee('autoRefresh: false', false);
});
