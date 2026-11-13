<?php

namespace App\Listeners;

use App\Events\StoryPublished;
use App\Jobs\GenerateReel;

/** A published story gets one auto reel per language version (when REELS_ENABLED). */
class QueueStoryReels
{
    public function handle(StoryPublished $event): void
    {
        if (! config('reels.enabled')) {
            return;
        }

        foreach ($event->story->versions()->pluck('language') as $language) {
            GenerateReel::dispatch($event->story->id, $language);
        }
    }
}
