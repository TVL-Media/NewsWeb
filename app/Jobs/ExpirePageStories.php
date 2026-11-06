<?php

namespace App\Jobs;

use App\Models\PageStory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/** Every 15 minutes: remove 24-hour page story circles that have expired (and their images). */
class ExpirePageStories implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $disk = Storage::disk(config('filesystems.media'));

        PageStory::where('expires_at', '<=', now())->chunkById(200, function ($chunk) use ($disk) {
            foreach ($chunk as $item) {
                // Circles made from a story reuse the story's photo: only delete images uploaded for the circle.
                if (str_starts_with($item->image_path, 'page-stories/')) {
                    $disk->delete($item->image_path);
                }
                $item->delete();
            }
        });
    }
}
