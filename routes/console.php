<?php

use App\Jobs\BuildBriefingAudio;
use App\Jobs\ClusterItems;
use App\Jobs\DraftQuiz;
use App\Jobs\FetchFeeds;
use App\Jobs\GenerateDrafts;
use App\Models\Page;
use App\Models\PageStory;
use App\Models\Story;
use App\Models\StoryCluster;
use Illuminate\Support\Str;
use App\Jobs\ClosePolls;
use App\Jobs\ExpirePageStories;
use App\Jobs\PickMorning5;
use App\Jobs\UpdateStreaks;
use App\Models\Briefing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduler (run `php artisan schedule:work` locally, or a cron calling `schedule:run` every
| minute in production). Times are IST (config('app.timezone') = Asia/Kolkata).
*/

// Newsroom
Schedule::job(new FetchFeeds)->everyFiveMinutes()->name('fetch-feeds')->withoutOverlapping()->onOneServer();
Schedule::job(new DraftQuiz)->dailyAt('21:00')->timezone(config('app.timezone'))->name('draft-quiz')->onOneServer();

// Habit features
Schedule::job(new PickMorning5)->dailyAt('06:00')->timezone(config('app.timezone'))->name('pick-morning5')->onOneServer();
Schedule::job(new UpdateStreaks)->dailyAt('00:05')->timezone(config('app.timezone'))->name('update-streaks')->onOneServer();
Schedule::job(new ClosePolls)->everyFifteenMinutes()->name('close-polls')->onOneServer();
Schedule::job(new ExpirePageStories)->everyFifteenMinutes()->name('expire-page-stories')->onOneServer();

// Manual helpers
Artisan::command('newsroom:run {--feed= : Only this feed_sources id}', function () {
    config(['queue.default' => 'sync']); // run fetch → cluster → draft → checks → route right away
    $before = Story::count();
    FetchFeeds::dispatchSync($this->option('feed') ? (int) $this->option('feed') : null);
    ClusterItems::dispatchSync(); // also picks up items added earlier
    foreach (StoryCluster::whereNull('story_id')->pluck('id') as $id) {
        GenerateDrafts::dispatchSync($id);
    }
    $new = Story::with('aiCheck')->latest('id')->take(Story::count() - $before)->get();
    $this->table(['Story', 'Status', 'Decision', 'Why'], $new->map(fn ($s) => [
        Str::limit($s->slug, 50), $s->status, $s->aiCheck?->decision, implode('; ', $s->aiCheck?->reasons ?? []) ?: 'all checks passed',
    ]));
    $this->info($new->count().' new stories.');
})->purpose('Run the newsroom pipeline now: fetch feeds, cluster, AI drafts, checks, auto-publish / approval');

Artisan::command('demo:page-stories', function () {
    PageStory::where('expires_at', '<=', now())->delete();
    $made = 0;
    foreach (Page::all() as $page) {
        $story = Story::published()->where('page_id', $page->id)->whereNotNull('photo_path')->latest('published_at')->first();
        if ($story) {
            PageStory::updateOrCreate(
                ['page_id' => $page->id, 'story_id' => $story->id],
                ['image_path' => $story->photo_path, 'expires_at' => now()->addHours(24)],
            );
            $made++;
        }
    }
    $this->info("{$made} page story circles are live for the next 24 hours.");
})->purpose('Demo: give every page a fresh 24-hour story circle from its latest story');

Artisan::command('quiz:draft {date?}', function (?string $date = null) {
    DraftQuiz::dispatchSync($date);
    $this->info('Quiz drafted (if there were enough stories and no quiz existed for that date).');
})->purpose("Draft a quiz for a date (default: tomorrow, IST) from today's top stories");

Artisan::command('briefing:pick {date?}', function (?string $date = null) {
    config(['queue.default' => 'sync']); // run the follow-up audio job right away
    PickMorning5::dispatchSync($date);
    $this->info('Morning 5 picked and audio built.');
})->purpose('Pick the Morning 5 for a date (default: today, IST) and build its audio');

Artisan::command('briefing:audio {date?}', function (?string $date = null) {
    $briefing = Briefing::whereDate('date', $date ?? now(config('app.timezone'))->toDateString())->firstOrFail();
    BuildBriefingAudio::dispatchSync($briefing->id);
    $this->info('Briefing audio rebuilt.');
})->purpose('Rebuild the joined Morning 5 audio for a date');
