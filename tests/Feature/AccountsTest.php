<?php

use App\Contracts\SmsSender;
use App\Models\Category;
use App\Models\City;
use App\Models\Comment;
use App\Models\Page;
use App\Models\ReadingEvent;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

/** Captures OTPs instead of sending SMS. */
class FakeSms implements SmsSender
{
    public array $sent = [];

    public function sendOtp(string $phone, string $code): void
    {
        $this->sent[$phone] = $code;
    }
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->sms = new FakeSms;
    $this->app->instance(SmsSender::class, $this->sms);
    $this->story = Story::published()->where('slug', 'india-beat-new-zealand-by-seven-wickets-to-clinch-test-series-2-0')->first();
    $this->reader = User::where('phone', '9876543210')->first();
});

describe('OTP login', function () {
    it('sends an OTP and logs in a new user, who goes to onboarding', function () {
        $this->postJson('/auth/otp/send', ['phone' => '9123456789'])->assertOk()->assertJson(['sent' => true]);
        $code = $this->sms->sent['9123456789'];

        $this->postJson('/auth/otp/verify', ['phone' => '9123456789', 'code' => $code])
            ->assertOk()
            ->assertJson(['new' => true, 'redirect' => route('onboarding')]);

        $this->assertAuthenticated();
        expect(User::where('phone', '9123456789')->exists())->toBeTrue();
    });

    it('sends an existing, onboarded reader back to where they were', function () {
        $this->postJson('/auth/otp/send', ['phone' => '9876543210']);

        $this->postJson('/auth/otp/verify', ['phone' => '9876543210', 'code' => $this->sms->sent['9876543210']])
            ->assertOk()->assertJson(['new' => false]);
        $this->assertAuthenticatedAs($this->reader);
    });

    it('rejects a wrong code, invalid numbers and too-fast resends', function () {
        $this->postJson('/auth/otp/send', ['phone' => '12345'])->assertUnprocessable();
        $this->postJson('/auth/otp/send', ['phone' => '9123456789'])->assertOk();
        $this->postJson('/auth/otp/send', ['phone' => '9123456789'])->assertUnprocessable(); // within 30 s

        $wrong = $this->sms->sent['9123456789'] === '111111' ? '222222' : '111111';
        $this->postJson('/auth/otp/verify', ['phone' => '9123456789', 'code' => $wrong])->assertUnprocessable();
        $this->assertGuest();
    });

    it('locks the code after 5 wrong attempts', function () {
        $this->postJson('/auth/otp/send', ['phone' => '9123456789']);
        $code = $this->sms->sent['9123456789'];
        $wrong = $code === '111111' ? '222222' : '111111';

        foreach (range(1, 5) as $i) {
            $this->postJson('/auth/otp/verify', ['phone' => '9123456789', 'code' => $wrong]);
        }

        $this->postJson('/auth/otp/verify', ['phone' => '9123456789', 'code' => $code])->assertUnprocessable();
        $this->assertGuest();
    });

    it('accepts the development OTP outside production, but never in production', function () {
        config(['services.sms.dev_code' => '123456']);

        $this->postJson('/auth/otp/send', ['phone' => '9123456789'])->assertOk();
        expect($this->sms->sent['9123456789'])->toBe('123456');
        $this->postJson('/auth/otp/verify', ['phone' => '9123456789', 'code' => '123456'])->assertOk();
        $this->assertAuthenticated();

        $this->app['env'] = 'production';
        expect(\App\Services\OtpService::devCode())->toBeNull();
    });

    it('logs out', function () {
        $this->actingAs($this->reader)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    });
});

describe('onboarding', function () {
    it('keeps new users on onboarding until they finish, then saves their choices', function () {
        $user = User::factory()->fresh()->create();
        $lucknow = City::where('name_en', 'Lucknow')->first();
        $cats = Category::whereIn('slug', ['cricket', 'tech'])->pluck('id')->all();

        $this->actingAs($user)->get('/')->assertRedirect(route('onboarding'));
        $this->get('/onboarding')->assertOk()->assertSee('Choose your language')->assertSee('POPULAR CITIES');

        $this->post('/onboarding', ['language' => 'hi', 'categories' => [], 'city_id' => $lucknow->id])->assertSessionHasErrors('categories');

        $this->post('/onboarding', ['language' => 'hi', 'categories' => $cats, 'city_id' => $lucknow->id])
            ->assertRedirect(route('for-you'));

        $user->refresh();
        expect($user->onboarded_at)->not->toBeNull()
            ->and($user->language)->toBe('hi')
            ->and($user->categories()->pluck('categories.id')->sort()->values()->all())->toBe(collect($cats)->sort()->values()->all())
            ->and($user->followedPages()->where('slug', 'lucknow')->exists())->toBeTrue();

        $this->get('/onboarding')->assertRedirect(route('home'));
    });
});

describe('For You', function () {
    it('shows only stories from chosen categories and followed pages, then "all caught up"', function () {
        $user = User::factory()->create();
        $user->categories()->attach(Category::where('slug', 'tech')->value('id'));

        $response = $this->actingAs($user)->get('/for-you')->assertOk()->assertSee("You're all caught up");

        $tech = Story::published()->whereHas('category', fn ($q) => $q->where('slug', 'tech'))->with('versions')->get();
        $cricket = Story::published()->whereHas('category', fn ($q) => $q->where('slug', 'cricket'))->with('versions')->get();
        $tech->each(fn ($s) => $response->assertSee($s->version('en')->headline));
        $cricket->each(fn ($s) => $response->assertDontSee($s->version('en')->headline));
        $response->assertDontSee('story-ring', false); // no story circles
    });

    it('puts read stories under "Earlier", after the caught-up marker', function () {
        $user = User::factory()->create();
        $user->categories()->attach($this->story->category_id);
        ReadingEvent::query()->insertOrIgnore(['user_id' => $user->id, 'story_id' => $this->story->id, 'read_at' => now(), 'read_on' => now()->toDateString()]);

        $html = $this->actingAs($user)->get('/for-you')->assertOk()->assertSee('Scroll for earlier stories')->getContent();
        $caughtUp = strpos($html, "You&#039;re all caught up");
        $readCard = strpos($html, e($this->story->version('en')->headline));

        expect($caughtUp)->not->toBeFalse()
            ->and($readCard)->toBeGreaterThan($caughtUp);
    });

    it('never goes blank once everything has been read', function () {
        $user = User::factory()->create();
        $user->categories()->attach($this->story->category_id);
        Story::published()->where('category_id', $this->story->category_id)->pluck('id')->each(fn ($id) => ReadingEvent::query()->insertOrIgnore([
            'user_id' => $user->id, 'story_id' => $id, 'read_at' => now(), 'read_on' => now()->toDateString(),
        ]));

        $html = $this->actingAs($user)->get('/for-you')->assertOk()->getContent();
        expect(strpos($html, "You&#039;re all caught up"))->toBeLessThan(strpos($html, e($this->story->version('en')->headline)))
            ->and(substr_count($html, '<article'))->toBeGreaterThan(0);
    });
});

describe('engagement', function () {
    it('likes and unlikes a story and keeps the count right', function () {
        $before = $this->story->likes_count;

        $this->actingAs($this->reader)->postJson("/stories/{$this->story->id}/like")->assertJson(['liked' => true, 'likes' => $before + 1]);
        $this->postJson("/stories/{$this->story->id}/like")->assertJson(['likes' => $before + 1]); // idempotent
        $this->deleteJson("/stories/{$this->story->id}/like")->assertJson(['liked' => false, 'likes' => $before]);
    });

    it('sets, changes and clears a reaction', function () {
        $this->actingAs($this->reader)->putJson("/stories/{$this->story->id}/reaction", ['type' => 'surprising'])->assertJson(['reaction' => 'surprising']);
        $this->putJson("/stories/{$this->story->id}/reaction", ['type' => 'good_news'])->assertJson(['reaction' => 'good_news']);
        expect($this->story->reactions()->count())->toBe(1);
        $this->putJson("/stories/{$this->story->id}/reaction", ['type' => null])->assertJson(['reaction' => null]);
        $this->putJson("/stories/{$this->story->id}/reaction", ['type' => 'angry'])->assertUnprocessable();
    });

    it('saves and unsaves, and shows saved stories in the library', function () {
        $this->actingAs($this->reader)->postJson("/stories/{$this->story->id}/save")->assertJson(['saved' => true]);
        $this->get('/account/saved')->assertOk()->assertSee($this->story->version('en')->headline);
        $this->deleteJson("/stories/{$this->story->id}/save")->assertJson(['saved' => false]);
        $this->get('/account/saved')->assertDontSee($this->story->version('en')->headline);
    });

    it('follows and unfollows a page', function () {
        $page = Page::where('slug', 'health-watch')->first();

        $this->actingAs($this->reader)->postJson('/pages/health-watch/follow')->assertJson(['following' => true]);
        expect($this->reader->followedPages()->whereKey($page->id)->exists())->toBeTrue();
        $this->deleteJson('/pages/health-watch/follow')->assertJson(['following' => false]);
        expect($this->reader->followedPages()->whereKey($page->id)->exists())->toBeFalse();
    });

    it('records one read per story per IST day', function () {
        $this->actingAs($this->reader)->postJson("/stories/{$this->story->id}/read")->assertOk();
        $this->postJson("/stories/{$this->story->id}/read")->assertOk();

        expect(ReadingEvent::where('user_id', $this->reader->id)->where('story_id', $this->story->id)->count())->toBe(1);
    });

    it('makes a 1080×1350 share image and returns the link', function () {
        $data = $this->actingAs($this->reader)->postJson("/stories/{$this->story->id}/share", ['channel' => 'image', 'lang' => 'hi'])
            ->assertOk()->json();

        expect($data['url'])->toBe(route('story.show', $this->story));
        $path = 'shares/'.basename(parse_url($data['image_url'], PHP_URL_PATH));
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        expect([$w, $h])->toBe([1080, 1350])
            ->and($this->story->fresh()->shares_count)->toBe($this->story->shares_count + 1);
    });

    it('reports a comment once per reader', function () {
        $comment = Comment::first();

        $this->actingAs($this->reader)->postJson('/reports', ['target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'abusive'])->assertCreated();
        $this->postJson('/reports', ['target_type' => 'comment', 'target_id' => $comment->id, 'reason' => 'spam'])->assertCreated();
        $this->postJson('/reports', ['target_type' => 'story', 'target_id' => 999999, 'reason' => 'spam'])->assertNotFound();

        expect(Report::where('target_type', 'comment')->where('target_id', $comment->id)->count())->toBe(1);
    });

    it('asks guests to log in', function () {
        $this->postJson("/stories/{$this->story->id}/like")->assertUnauthorized();
        $this->postJson('/pages/cricket/follow')->assertUnauthorized();
        $this->postJson("/stories/{$this->story->id}/comments", ['body' => 'Hello'])->assertUnauthorized();
    });
});

describe('comments', function () {
    it('posts a comment and a reply, and lists them', function () {
        $this->actingAs($this->reader);
        $id = $this->postJson("/stories/{$this->story->id}/comments", ['body' => 'Great series win!'])->assertCreated()->json('comment.id');
        $this->postJson("/stories/{$this->story->id}/comments", ['body' => 'Agreed!', 'parent_id' => $id])->assertCreated();

        $list = $this->getJson("/stories/{$this->story->id}/comments")->assertOk()->json('comments');
        expect($list[0]['body'])->toBe('Great series win!')
            ->and($list[0]['replies'][0]['body'])->toBe('Agreed!')
            ->and($this->story->fresh()->comments_count)->toBe($this->story->comments_count + 2);
    });

    it('blocks profanity in English, Hindi and Hinglish', function (string $body) {
        $this->actingAs($this->reader)->postJson("/stories/{$this->story->id}/comments", ['body' => $body])->assertUnprocessable();
    })->with(['What a f*cking joke', 'tu chutiya hai', 'ये हरामी है', 'sh1t take']);

    it('lets accounts younger than 24 hours post only 3 comments a day', function () {
        $newbie = User::factory()->create();
        $this->actingAs($newbie);

        foreach (range(1, 3) as $i) {
            $this->postJson("/stories/{$this->story->id}/comments", ['body' => "Comment number {$i}"])->assertCreated();
        }
        $this->postJson("/stories/{$this->story->id}/comments", ['body' => 'One more'])->assertUnprocessable();

        // Older accounts aren't limited.
        $this->actingAs($this->reader);
        foreach (range(1, 4) as $i) {
            $this->postJson("/stories/{$this->story->id}/comments", ['body' => "Reader comment {$i}"])->assertCreated();
        }
    });
});

describe('account', function () {
    it('shows the account card, stats, library and preferences', function () {
        $this->actingAs($this->reader)->get('/account')->assertOk()
            ->assertSee('Riya Sharma')
            ->assertSee('day streak')->assertSee('12')
            ->assertSee("today's quiz")
            ->assertSee('read this week')
            ->assertSee('YOUR LIBRARY')->assertSee('Saved stories')->assertSee('Pages you follow')->assertSee('Reading history')
            ->assertSee('PREFERENCES')->assertSee('Text size')->assertSee('Dark mode')
            ->assertSee('Help and grievance')->assertSee('Log out');
    });

    it('updates name, city, language and preferences', function () {
        $delhi = City::where('name_en', 'Delhi')->first();

        $this->actingAs($this->reader)->patchJson('/account', [
            'name' => 'Riya S', 'city_id' => $delhi->id, 'language' => 'hi',
            'text_size' => 'large', 'theme' => 'dark', 'notifications_per_day' => 5,
        ])->assertOk();

        $this->reader->refresh();
        expect([$this->reader->name, $this->reader->city_id, $this->reader->language, $this->reader->text_size, $this->reader->theme, $this->reader->notifications_per_day])
            ->toBe(['Riya S', $delhi->id, 'hi', 'large', 'dark', 5]);

        $this->get('/account')->assertSee('data-text-size="large"', false)->assertSee('data-theme="dark"', false);
    });

    it('lists every library section', function (string $type) {
        $this->actingAs($this->reader)->get("/account/{$type}")->assertOk();
    })->with(['saved', 'following', 'liked', 'history']);

    it('deletes the account', function () {
        $this->actingAs($this->reader)->delete('/account')->assertRedirect('/');
        $this->assertGuest();
        expect(User::find($this->reader->id))->toBeNull();
    });
});
