<?php

namespace App\Jobs;

use App\Models\Story;
use App\Services\Newsroom\StoryPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Auto-publish when every check passed and confidence ≥ the threshold (decision "auto");
 * otherwise status → pending_approval and NotifyEditor.
 */
class RouteStory implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storyId) {}

    public function handle(StoryPublisher $publisher): void
    {
        $story = Story::with('aiCheck')->find($this->storyId);
        if (! $story || $story->status !== 'draft' || ! $story->aiCheck) {
            return;
        }

        if ($story->aiCheck->decision === 'auto') {
            $publisher->publish($story, 'auto');

            return;
        }

        $story->update(['status' => 'pending_approval', 'publish_mode' => 'manual']);
        NotifyEditor::dispatch($story->id);
    }
}
