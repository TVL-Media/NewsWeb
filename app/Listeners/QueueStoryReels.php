<?php

namespace App\Listeners;

use App\Events\StoryPublished;
use App\Jobs\GenerateReel;

/**
 * Auto reels: a published story gets one reel per language version — only when REELS_ENABLED and
 * REELS_AUTO_FROM_STORIES are both on. Off by default: reels come from admin uploads.
 */
class QueueStoryReels
{
    public function handle(StoryPublished $event): void
    {
        if (! config('reels.enabled') || ! config('reels.auto_from_stories')) {
            return;
        }

        foreach ($event->story->versions()->pluck('language') as $language) {
            GenerateReel::dispatch($event->story->id, $language);
        }
    }
}
