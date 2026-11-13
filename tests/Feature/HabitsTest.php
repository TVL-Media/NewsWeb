<?php

use App\Jobs\ClosePolls;
use App\Jobs\ExpirePageStories;
use App\Jobs\PickMorning5;
use App\Jobs\UpdateStreaks;
use App\Models\Briefing;
use App\Models\PageStory;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\ReadingEvent;
use App\Models\Story;
use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->reader = User::where('phone', '9876543210')->first();
});

/** Helper: N reads for a user on a given IST date. */
function readsOn(User $user, string $date, int $n): void
{
    Story::published()->limit($n)->pluck('id')->each(fn ($id) => ReadingEvent::query()->insertOrIgnore([
        'user_id' => $user->id, 'story_id' => $id, 'read_on' => $date, 'read_at' => now(),
    ]));
}

describe('Morning 5 and briefing', function () {
    it('builds the joined EN and HI audio with per-story timestamps', function () {
        $briefing = Briefing::today();

        expect($briefing->story_ids)->toHaveCount(5)
            ->and($briefing->offsets('en'))->toHaveCount(5)
            ->and($briefing->offsets('en')[0])->toBe(0)
            ->and($briefing->audio_seconds)->toBeGreaterThan(20);
        Storage::disk('public')->assertExists($briefing->audio_path_en);
        Storage::disk('public')->assertExists($briefing->audio_path_hi);

        // The joined WAV is as long as its parts.
        $wav = Storage::disk('public')->get($briefing->audio_path_en);
        $dataBytes = unpack('V', substr($wav, strpos($wav, 'data') + 4, 4))[1];
        expect((int) round($dataBytes / 8000))->toBe($briefing->audio_seconds);
    });

    it('picks the Morning 5 at 06:00 without overriding an editor\'s choice', function () {
        Briefing::query()->delete();
        PickMorning5::dispatchSync();
        $auto = Briefing::today();
        expect($auto->story_ids)->toHaveCount(5);

        $editor = array_reverse($auto->story_ids);
        $auto->update(['story_ids' => $editor]);
        PickMorning5::dispatchSync();
        expect(Briefing::today()->story_ids)->toBe($editor);
    });

    it('renders the player, list, quiz card, week card and polls when audio is on', function () {
        config(['services.audio.enabled' => true]);
        $briefing = Briefing::today();
        $first = Story::with('versions')->find($briefing->story_ids[0]);

        $this->get('/briefing')->assertOk()
            ->assertSee("Today's Briefing")->assertSee('Morning 5')->assertSee('In this briefing')
            ->assertSee($first->version('en')->headline)
            ->assertSee(basename($briefing->audio_path_en), false)
            ->assertSee('Daily quiz')->assertSee('This week')->assertSee('Polls');

        $this->actingAs($this->reader)->get('/briefing')->assertOk()->assertSee('12-day streak');
    });

    it('shows the Top 5 trending stories instead of the audio player when audio is off', function () {
        $top = \App\Support\Rails::trending();

        $this->get('/briefing')->assertOk()
            ->assertSee('Top 5')->assertSee('Trending right now')
            ->assertSee(route('story.show', $top[0]), false)
            ->assertSee($top[4]->version('en')->headline)
            ->assertDontSee('<audio', false)->assertDontSee('In this briefing')
            ->assertSee('Daily quiz')->assertSee('Polls');
    });
});

describe('daily quiz', function () {
    it('checks answers one by one, scores the quiz and counts the streak day', function () {
        $quiz = Quiz::today()->load('questions');
        $this->actingAs($this->reader)->get('/quiz')->assertOk()
            ->assertSee($quiz->questions[0]->question)
            // Answers and explanations aren't in the page data (JSON keys are written as "key").
            ->assertDontSee('"correct_index"', false)
            ->assertDontSee('"explanation"', false);

        foreach ($quiz->questions as $n => $q) {
            $choice = $n === 0 ? ($q->correct_index + 1) % 4 : $q->correct_index; // 1 wrong, 4 right
            $res = $this->postJson('/quiz/answers', ['question_id' => $q->id, 'choice' => $choice])->assertOk();
            expect($res->json('correct'))->toBe($n !== 0)
                ->and($res->json('correct_index'))->toBe($q->correct_index);
        }

        expect($res->json('done'))->toBeTrue()
            ->and($res->json('score'))->toBe(4)
            ->and($res->json('streak'))->toBe(13)   // seeded 12, last day yesterday → 13
            ->and($res->json('streak_increased'))->toBeTrue();

        $attempt = QuizAttempt::where('user_id', $this->reader->id)->first();
        expect($attempt->completed_at)->not->toBeNull();

        // Answers can't be changed afterwards.
        $this->postJson('/quiz/answers', ['question_id' => $quiz->questions[0]->id, 'choice' => $quiz->questions[0]->correct_index])
            ->assertJson(['correct' => false]);

        // Result image
        $img = $this->postJson('/quiz/share')->assertOk()->json('image_url');
        Storage::disk('public')->assertExists('shares/'.basename(parse_url($img, PHP_URL_PATH)));
    });

    it('treats a timed-out question as wrong', function () {
        $q = Quiz::today()->questions()->first();

        $this->actingAs($this->reader)->postJson('/quiz/answers', ['question_id' => $q->id, 'choice' => null])
            ->assertOk()->assertJson(['correct' => false]);
    });

    it('needs login', function () {
        $this->get('/quiz')->assertRedirect(route('home', ['login' => 1]));
    });
});

describe('streaks', function () {
    it('counts a day after 3 reads, once per day', function () {
        $user = User::factory()->create();
        $stories = Story::published()->limit(4)->get();

        $this->actingAs($user);
        $this->postJson("/stories/{$stories[0]->id}/read")->assertJson(['read_today' => 1, 'streak' => null]);
        $this->postJson("/stories/{$stories[1]->id}/read");
        $this->postJson("/stories/{$stories[2]->id}/read")->assertJson(['read_today' => 3, 'streak' => 1, 'streak_increased' => true]);
        $this->postJson("/stories/{$stories[3]->id}/read")->assertJson(['streak' => 1, 'streak_increased' => false]);
    });

    it('continues from yesterday and restarts after a gap', function () {
        $user = User::factory()->create();
        $today = StreakService::today();
        Streak::create(['user_id' => $user->id, 'current' => 5, 'longest' => 5, 'last_day' => $today->subDays(3)->toDateString(), 'freezes_left' => 2, 'freezes_month' => $today->format('Y-m')]);
        readsOn($user, $today->toDateString(), 3);

        [$streak] = app(StreakService::class)->recordActivity($user);
        expect($streak->current)->toBe(1)->and($streak->longest)->toBe(5);
    });

    it('uses a freeze for a missed day at 00:05, and resets when none are left', function () {
        $today = StreakService::today();
        $month = $today->format('Y-m');
        $a = Streak::create(['user_id' => User::factory()->create()->id, 'current' => 7, 'longest' => 7, 'last_day' => $today->subDays(2)->toDateString(), 'freezes_left' => 2, 'freezes_month' => $month]);
        $b = Streak::create(['user_id' => User::factory()->create()->id, 'current' => 4, 'longest' => 9, 'last_day' => $today->subDays(2)->toDateString(), 'freezes_left' => 0, 'freezes_month' => $month]);
        $c = Streak::create(['user_id' => User::factory()->create()->id, 'current' => 3, 'longest' => 3, 'last_day' => $today->subDay()->toDateString(), 'freezes_left' => 2, 'freezes_month' => $month]);

        UpdateStreaks::dispatchSync();

        expect($a->fresh()->current)->toBe(7)->and($a->fresh()->freezes_left)->toBe(1)
            ->and($a->fresh()->last_day->toDateString())->toBe($today->subDay()->toDateString())
            ->and($b->fresh()->current)->toBe(0)
            ->and($c->fresh()->current)->toBe(3)->and($c->fresh()->freezes_left)->toBe(2);
    });

    it('gives 2 fresh freezes each month', function () {
        $today = StreakService::today();
        $s = Streak::create(['user_id' => User::factory()->create()->id, 'current' => 2, 'longest' => 2, 'last_day' => $today->subDays(2)->toDateString(), 'freezes_left' => 0, 'freezes_month' => '2020-01']);

        UpdateStreaks::dispatchSync();

        expect($s->fresh()->current)->toBe(2)->and($s->fresh()->freezes_left)->toBe(1)->and($s->fresh()->freezes_month)->toBe($today->format('Y-m'));
    });

    it('shows the last 7 days on the week card', function () {
        readsOn($this->reader, StreakService::today()->toDateString(), 3);
        $week = app(StreakService::class)->week($this->reader);

        expect($week)->toHaveCount(7)
            ->and($week[6]['today'])->toBeTrue()
            ->and($week[6]['done'])->toBeTrue();
    });
});

describe('polls', function () {
    it('allows one vote per reader and returns results', function () {
        $poll = Poll::live()->with('options')->first();
        $option = $poll->options[1];

        $this->actingAs($this->reader)->postJson("/polls/{$poll->id}/vote", ['option_id' => $option->id])
            ->assertOk()->assertJson(['voted' => $option->id]);
        expect($option->fresh()->votes_count)->toBe($option->votes_count + 1);

        $this->postJson("/polls/{$poll->id}/vote", ['option_id' => $poll->options[0]->id])
            ->assertStatus(409)->assertJson(['voted' => $option->id]);
        expect($poll->options[0]->fresh()->votes_count)->toBe($poll->options[0]->votes_count);
    });

    it('refuses votes on closed polls and closes polls at ends_at', function () {
        $closed = Poll::where('status', 'closed')->with('options')->first();
        $this->actingAs($this->reader)->postJson("/polls/{$closed->id}/vote", ['option_id' => $closed->options[0]->id])->assertUnprocessable();

        $live = Poll::live()->first();
        $live->update(['ends_at' => now()->subMinute()]);
        ClosePolls::dispatchSync();
        expect($live->fresh()->status)->toBe('closed');
    });

    it('creates reader polls as pending, with checks', function () {
        $this->actingAs($this->reader);

        $this->postJson('/polls', ['question' => 'Should schools start later in winter?', 'options' => ['Yes', 'No', 'Not sure'], 'days' => 3])
            ->assertCreated()->assertJson(['status' => 'pending']);
        $poll = Poll::where('created_by', $this->reader->id)->with('options')->first();
        expect($poll->status)->toBe('pending')->and($poll->options)->toHaveCount(3)
            ->and(Poll::live()->whereKey($poll->id)->exists())->toBeFalse();

        $this->postJson('/polls', ['question' => 'Too short', 'options' => ['A'], 'days' => 2])->assertUnprocessable();
        $this->postJson('/polls', ['question' => 'Is this chutiya question allowed?', 'options' => ['Yes', 'No'], 'days' => 1])->assertUnprocessable();

        $this->postJson('/polls', ['question' => 'Second poll question here?', 'options' => ['A', 'B'], 'days' => 1])->assertCreated();
        $this->postJson('/polls', ['question' => 'Third poll question here?', 'options' => ['A', 'B'], 'days' => 1])->assertCreated();
        $this->postJson('/polls', ['question' => 'Fourth poll question here?', 'options' => ['A', 'B'], 'days' => 1])->assertUnprocessable();
    });
});

it('removes expired page stories', function () {
    $item = PageStory::first();
    $item->update(['expires_at' => now()->subMinute()]);

    ExpirePageStories::dispatchSync();

    expect(PageStory::find($item->id))->toBeNull();
});

it('schedules the daily and 15-minute jobs', function () {
    $this->artisan('schedule:list')->assertSuccessful()
        ->expectsOutputToContain('pick-morning5')
        ->expectsOutputToContain('update-streaks')
        ->expectsOutputToContain('close-polls')
        ->expectsOutputToContain('expire-page-stories');
});
