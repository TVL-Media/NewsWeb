<?php

namespace App\Jobs;

use App\Models\Reel;
use App\Services\Reels\ReelBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Editor-uploaded video → 720p/480p MP4 + thumbnail. Uploads always go to the approval queue. */
class ProcessUploadedReel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $reelId) {}

    public function handle(ReelBuilder $builder): void
    {
        $reel = Reel::find($this->reelId);
        if (! $reel || $reel->source_type !== 'upload') {
            return;
        }

        $reel->update(['status' => 'processing', 'error_message' => null]);
        $old = array_filter($reel->only(['video_720_path', 'video_480_path', 'thumbnail_path']));

        try {
            $files = $builder->fromUpload($reel);
        } catch (Throwable $e) {
            $reel->markFailed($e->getMessage());
            report($e);

            return;
        }

        $reel->update([...$files, 'status' => 'pending_approval', 'published_at' => null]);
        $builder->deleteFiles($old);
    }

    public function failed(?Throwable $e): void
    {
        Reel::find($this->reelId)?->markFailed($e?->getMessage() ?? 'The reel job stopped before it finished.');
    }
}
