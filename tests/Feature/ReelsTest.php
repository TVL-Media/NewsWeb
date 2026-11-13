<?php

use App\Events\StoryPublished;
use App\Jobs\GenerateReel;
use App\Jobs\ProcessUploadedReel;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Reel;
use App\Models\ReelView;
use App\Models\Report;
use App\Models\Save;
use App\Models\Share;
use App\Models\Story;
use App\Models\User;
use App\Services\Reels\Ffmpeg;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

/**
 * Fake ffmpeg/ffprobe: ffprobe reports $seconds for every file; ffmpeg "writes" each output file
 * it is given, or fails with $error.
 */
function fakeFfmpeg(float $seconds = 6.0, ?string $error = null, bool $audio = true): void
{
    Process::fake(['*' => function (PendingProcess $process) use ($seconds, $error, $audio) {
        $args = (array) $process->command;

        if (str_contains(implode(' ', $args), 'format=duration')) {
            return Process::result(sprintf('%.6f', $seconds));
        }
        if (str_contains(implode(' ', $args), 'stream=index')) {
            return Process::result($audio ? '1' : '');
        }
        if (in_array('-version', $args, true)) {
            return Process::result('ffmpeg version 9.0');
        }
        if ($error) {
            return Process::result(errorOutput: $error, exitCode: 1);
        }

        foreach ($args as $i => $arg) {
            if (preg_match('/\.(mp4|jpg)$/', $arg) && ($args[$i - 1] ?? '') !== '-i') {
                file_put_contents($arg, 'fake-'.basename($arg));
            }
        }

        return Process::result();
    }]);
}

function lowRiskStory(): Story
{
    return Story::published()->whereNotNull('photo_path')
        ->whereHas('aiCheck', fn ($q) => $q->where('risk_level', 'low'))->orderByDesc('importance')->firstOrFail();
}

function sensitiveStory(): Story
{
    return Story::published()->whereHas('aiCheck', fn ($q) => $q->where('risk_level', 'sensitive'))->firstOrFail();
}

it('lets comments, saves, shares, likes and reports belong to a reel', function () {
    $story = lowRiskStory();
    $reel = Reel::create([
        'story_id' => $story->id, 'page_id' => $story->page_id, 'category_id' => $story->category_id,
        'language' => 'en', 'title' => 'Test reel', 'slug' => 'test-reel', 'status' => 'published', 'published_at' => now(),
    ]);
    $user = User::where('phone', '9876543210')->first();

    Comment::create(['reel_id' => $reel->id, 'user_id' => $user->id, 'body' => 'Nice reel']);
    Save::create(['reel_id' => $reel->id, 'user_id' => $user->id]);
    Share::create(['reel_id' => $reel->id, 'user_id' => $user->id, 'channel' => 'whatsapp']);
    $reel->likes()->create(['user_id' => $user->id]);
    $report = Report::create(['reporter_id' => $user->id, 'target_type' => 'reel', 'target_id' => $reel->id, 'reason' => 'Misleading']);
    ReelView::create(['reel_id' => $reel->id, 'user_id' => null, 'watched_seconds' => 12, 'completed' => true]);

    expect($reel->comments()->count())->toBe(1)
        ->and($reel->saves()->count())->toBe(1)
        ->and($reel->shares()->count())->toBe(1)
        ->and(Like::where('likeable_type', Reel::class)->count())->toBe(1)
        ->and($report->target()->is($reel))->toBeTrue()
        ->and($reel->views()->first()->completed)->toBeTrue()
        ->and(Comment::where('reel_id', $reel->id)->first()->story_id)->toBeNull();

    expect(fn () => Save::create(['reel_id' => $reel->id, 'user_id' => $user->id]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('queues one reel per language when a story is published and reels are enabled', function () {
    config(['reels.enabled' => true]);
    Queue::fake();
    $story = lowRiskStory();

    StoryPublished::dispatch($story);

    Queue::assertPushed(GenerateReel::class, 2);
    Queue::assertPushed(GenerateReel::class, fn ($job) => $job->storyId === $story->id && $job->language === 'hi');
});

it('queues nothing when reels are disabled', function () {
    config(['reels.enabled' => false]);
    Queue::fake();

    StoryPublished::dispatch(lowRiskStory());

    Queue::assertNotPushed(GenerateReel::class);
});

it('builds and auto-publishes a reel from a low-risk story', function () {
    fakeFfmpeg(seconds: 6.0);
    $story = lowRiskStory();

    GenerateReel::dispatchSync($story->id, 'en');

    $reel = Reel::where(['story_id' => $story->id, 'language' => 'en'])->firstOrFail();
    $disk = Storage::disk('public');

    expect($reel->status)->toBe('published')
        ->and($reel->published_at)->not->toBeNull()
        ->and($reel->source_type)->toBe('auto')
        ->and($reel->title)->toBe($story->version('en')->headline)
        ->and($reel->slug)->toBe($story->slug.'-reel')
        ->and($reel->page_id)->toBe($story->page_id)
        ->and($reel->category_id)->toBe($story->category_id)
        ->and($reel->duration_seconds)->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(45)
        ->and($reel->error_message)->toBeNull();

    foreach (['video_720_path', 'video_480_path', 'thumbnail_path'] as $column) {
        expect($reel->$column)->toStartWith("reels/{$reel->id}/");
        $disk->assertExists($reel->$column);
    }

    // Real JPEG thumbnail drawn by ReelSlides (not an ffmpeg output).
    [$w, $h] = getimagesizefromstring($disk->get($reel->thumbnail_path));
    expect([$w, $h])->toBe([720, 1280]);

    // Temporary TTS files are cleaned up.
    expect($disk->allFiles('reels/tmp'))->toBeEmpty();

    Process::assertRan(fn ($process) => in_array('+faststart', (array) $process->command, true)
        && in_array('libx264', (array) $process->command, true)
        && in_array('aac', (array) $process->command, true));
});

it('builds a Hindi reel from the Hindi version', function () {
    fakeFfmpeg();
    $story = lowRiskStory();

    GenerateReel::dispatchSync($story->id, 'hi');

    $reel = Reel::where(['story_id' => $story->id, 'language' => 'hi'])->firstOrFail();
    expect($reel->status)->toBe('published')
        ->and($reel->title)->toBe($story->version('hi')->headline)
        ->and($reel->slug)->toBe($story->slug.'-hi-reel');
});

it('sends a reel from a sensitive story to the approval queue', function () {
    fakeFfmpeg();
    $story = sensitiveStory();

    GenerateReel::dispatchSync($story->id, 'en');

    $reel = Reel::where('story_id', $story->id)->firstOrFail();
    expect($reel->status)->toBe('pending_approval')->and($reel->published_at)->toBeNull();
});

it('keeps the reel within the 20–45 second window', function (float $segment) {
    fakeFfmpeg(seconds: $segment);

    GenerateReel::dispatchSync(lowRiskStory()->id, 'en');

    expect(Reel::first()->duration_seconds)->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(45);
})->with([2.0, 6.0, 14.0]);

it('marks the reel failed and saves the FFmpeg error', function () {
    fakeFfmpeg(error: "Unknown encoder 'libx264'");

    GenerateReel::dispatchSync(lowRiskStory()->id, 'en');

    $reel = Reel::firstOrFail();
    expect($reel->status)->toBe('failed')
        ->and($reel->error_message)->toContain("Unknown encoder 'libx264'")
        ->and($reel->video_720_path)->toBeNull();
});

it('skips unchanged stories and replaces files when forced to re-generate', function () {
    fakeFfmpeg();
    $story = lowRiskStory();

    GenerateReel::dispatchSync($story->id, 'en');
    $first = Reel::firstOrFail();

    GenerateReel::dispatchSync($story->id, 'en');
    expect(Reel::firstOrFail()->video_720_path)->toBe($first->video_720_path);

    GenerateReel::dispatchSync($story->id, 'en', force: true);
    $second = Reel::firstOrFail();

    expect(Reel::count())->toBe(1)
        ->and($second->video_720_path)->not->toBe($first->video_720_path);
    Storage::disk('public')->assertMissing($first->video_720_path);
    Storage::disk('public')->assertExists($second->video_720_path);
});

it('re-generates when the story headline changes', function () {
    fakeFfmpeg();
    $story = lowRiskStory();
    GenerateReel::dispatchSync($story->id, 'en');

    $story->versions()->where('language', 'en')->update(['headline' => 'A brand new headline for the reel']);
    GenerateReel::dispatchSync($story->id, 'en');

    expect(Reel::firstOrFail()->title)->toBe('A brand new headline for the reel');
});

it('processes an uploaded video into the approval queue', function () {
    fakeFfmpeg(seconds: 30.0, audio: false);
    Storage::disk('public')->put('reels/uploads/clip.mov', 'raw-video');
    $story = lowRiskStory();

    $reel = Reel::create([
        'page_id' => $story->page_id, 'category_id' => $story->category_id, 'language' => 'en',
        'title' => 'Agency clip', 'slug' => 'agency-clip', 'source_type' => 'upload', 'source_path' => 'reels/uploads/clip.mov',
    ]);

    ProcessUploadedReel::dispatchSync($reel->id);

    $reel->refresh();
    expect($reel->status)->toBe('pending_approval')
        ->and($reel->duration_seconds)->toBe(30)
        ->and($reel->published_at)->toBeNull();
    Storage::disk('public')->assertExists($reel->video_480_path);

    // No audio track in the upload → a silent one is added.
    Process::assertRan(fn ($process) => str_contains(implode(' ', (array) $process->command), 'anullsrc'));
});

it('rejects uploads longer than REEL_MAX_SECONDS with a clear error', function () {
    fakeFfmpeg(seconds: 95.0);
    Storage::disk('public')->put('reels/uploads/long.mp4', 'raw-video');

    $reel = Reel::create([
        'language' => 'en', 'title' => 'Too long', 'slug' => 'too-long', 'source_type' => 'upload', 'source_path' => 'reels/uploads/long.mp4',
    ]);

    ProcessUploadedReel::dispatchSync($reel->id);

    expect($reel->fresh()->status)->toBe('failed')
        ->and($reel->fresh()->error_message)->toBe('The video is 95 seconds long; reels can be at most 60 seconds.');
});

it('deletes a reel\'s files when the reel is deleted', function () {
    fakeFfmpeg();
    GenerateReel::dispatchSync(lowRiskStory()->id, 'en');
    $reel = Reel::firstOrFail();
    $path = $reel->video_720_path;

    $reel->delete();

    Storage::disk('public')->assertMissing($path);
});

it('finds the other-language version of a reel', function () {
    fakeFfmpeg();
    $story = lowRiskStory();
    GenerateReel::dispatchSync($story->id, 'en');
    GenerateReel::dispatchSync($story->id, 'hi');

    $en = Reel::where('language', 'en')->first();
    expect($en->otherLanguage()?->language)->toBe('hi');
});

it('renders a real reel with FFmpeg', function () {
    $story = lowRiskStory();
    GenerateReel::dispatchSync($story->id, 'en');

    $reel = Reel::firstOrFail();
    expect($reel->status)->toBe('published', (string) $reel->error_message);

    $disk = Storage::disk('public');
    $ffprobe = app(Ffmpeg::class)->probeBinary();
    foreach (['video_720_path' => [720, 1280], 'video_480_path' => [480, 854]] as $column => [$w, $h]) {
        $out = Process::run([$ffprobe, '-v', 'error', '-show_entries', 'stream=codec_name,width,height', '-of', 'csv=p=0', $disk->path($reel->$column)])->output();
        expect($out)->toContain("h264,{$w},{$h}")->toContain('aac');

        // +faststart: the moov atom comes before the media data.
        $head = file_get_contents($disk->path($reel->$column), false, null, 0, 64 * 1024);
        expect(strpos($head, 'moov'))->toBeLessThan(strpos($head, 'mdat') ?: PHP_INT_MAX);
    }
    expect($reel->duration_seconds)->toBeGreaterThanOrEqual(20)->toBeLessThanOrEqual(45);
})->skip(fn () => ! app(Ffmpeg::class)->available(), 'FFmpeg is not installed (set FFMPEG_PATH).')->group('ffmpeg');
