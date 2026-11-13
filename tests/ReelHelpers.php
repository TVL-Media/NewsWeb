<?php

// Shared by the reel tests (loaded from tests/Pest.php).

use App\Models\Reel;
use App\Models\Story;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

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

function makeReel(Story $story, string $lang = 'en', array $attrs = []): Reel
{
    return Reel::create([
        'story_id' => $story->id, 'page_id' => $story->page_id, 'category_id' => $story->category_id,
        'language' => $lang, 'title' => $story->version($lang)->headline,
        'slug' => $story->slug.($lang === 'hi' ? '-hi' : '').'-reel',
        'video_720_path' => "reels/x/{$story->id}-{$lang}-720.mp4", 'video_480_path' => "reels/x/{$story->id}-{$lang}-480.mp4",
        'thumbnail_path' => "reels/x/{$story->id}-{$lang}-thumb.jpg", 'duration_seconds' => 28,
        'status' => 'published', 'published_at' => now()->subHour(),
        ...$attrs,
    ]);
}

function reader(): User
{
    return User::where('phone', '9876543210')->first();
}
