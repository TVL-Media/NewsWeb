<?php

namespace App\Listeners;

use App\Events\StoryPublished;
use App\Models\PageStory;
use App\Models\Story;

/**
 * Every published story with a page and a photo adds a 24-hour circle to that page,
 * so followers see fresh story circles on Home as news comes in.
 * Also called when a live story is edited (Story model "updated" hook): a story published
 * first and given its page/photo afterwards still gets its circle.
 */
class CreatePageStory
{
    public const HOURS = 24;

    public function handle(StoryPublished $event): void
    {
        self::sync($event->story);
    }

    /** Create or refresh the story's circle; it lasts 24 hours from publishing. */
    public static function sync(Story $story): void
    {
        if (! $story->page_id || ! $story->photo_path || ! in_array($story->status, ['published', 'corrected'], true)) {
            return;
        }

        $expires = ($story->published_at ?? now())->copy()->addHours(self::HOURS);
        if ($expires->isPast()) {
            return;
        }

        PageStory::updateOrCreate(
            ['page_id' => $story->page_id, 'story_id' => $story->id],
            ['image_path' => $story->photo_path, 'expires_at' => $expires],
        );
    }
}
