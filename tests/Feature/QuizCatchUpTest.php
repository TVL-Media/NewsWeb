<?php

use App\Models\Quiz;
use App\Models\Story;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['services.anthropic.key' => null]);
    Quiz::query()->delete();
});

it('drafts a missing quiz for today', function () {
    $this->artisan('quiz:catch-up')->expectsOutputToContain('Drafted quiz for '.now(config('app.timezone'))->toDateString());

    expect(Quiz::today())->not->toBeNull()
        ->and(Quiz::today()->questions()->count())->toBeGreaterThanOrEqual(3);
});

it('never replaces an existing quiz', function () {
    $quiz = Quiz::create(['date' => now(config('app.timezone'))->toDateString(), 'status' => 'draft']);

    $this->artisan('quiz:catch-up')->expectsOutputToContain('Nothing to catch up');

    expect(Quiz::count())->toBe(1)->and(Quiz::today()->id)->toBe($quiz->id);
});

it('also drafts tomorrow\'s quiz after 21:00 when the nightly run was missed', function () {
    $this->travelTo(now(config('app.timezone'))->setTime(22, 15));

    $this->artisan('quiz:catch-up');

    expect(Quiz::whereDate('date', now(config('app.timezone'))->toDateString())->exists())->toBeTrue()
        ->and(Quiz::whereDate('date', now(config('app.timezone'))->addDay()->toDateString())->exists())->toBeTrue();
});

it('still makes a quiz on a quiet day by using the last few days of stories', function () {
    // Nothing published in the last 24 hours, but plenty in the days before.
    Story::published()->update(['published_at' => now()->subDays(2)]);

    $this->artisan('quiz:draft', ['date' => now(config('app.timezone'))->toDateString()]);

    expect(Quiz::today()?->questions()->count())->toBeGreaterThanOrEqual(3);
});

it('is on the schedule every five minutes', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('quiz:catch-up');
});
