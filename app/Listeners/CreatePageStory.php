<?php

namespace App\Listeners;

use App\Events\StoryPublished;
use App\Models\PageStory;

/**
 * Every published story with a page and a photo adds a 24-hour circle to that page,
 * so followers see fresh story circles on Home as news comes in.
 */
class CreatePageStory
{
    public const HOURS = 24;

    public function handle(StoryPublished $event): void
    {
        $story = $event->story;

        if (! $story->page_id || ! $story->photo_path) {
            return;
        }

        PageStory::updateOrCreate(
            ['page_id' => $story->page_id, 'story_id' => $story->id],
            ['image_path' => $story->photo_path, 'expires_at' => now()->addHours(self::HOURS)],
        );
    }
}
