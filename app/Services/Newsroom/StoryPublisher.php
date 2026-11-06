<?php

namespace App\Services\Newsroom;

use App\Events\StoryPublished;
use App\Jobs\GenerateAudio;
use App\Models\Admin;
use App\Models\Approval;
use App\Models\Story;
use App\Support\PageCache;
use Illuminate\Support\Facades\DB;

/**
 * Publishing side effects in one place (auto-publish, editor approval, edits):
 * GenerateAudio (both languages), clear page caches, StoryPublished event (sitemaps, Milestone 6).
 */
class StoryPublisher
{
    public function publish(Story $story, string $mode, ?Admin $editor = null): Story
    {
        DB::transaction(function () use ($story, $mode, $editor) {
            $story->forceFill([
                'status' => $story->correction_label ? 'corrected' : 'published',
                'publish_mode' => $mode,
                'published_at' => $story->published_at && $story->published_at->isPast() ? $story->published_at : now(),
                'approved_by' => $editor?->id ?? $story->approved_by,
            ])->save();
        });

        GenerateAudio::dispatch($story->id);
        PageCache::flush();
        StoryPublished::dispatch($story);

        return $story;
    }

    public function approve(Story $story, Admin $editor, bool $edited = false): Story
    {
        Approval::create([
            'story_id' => $story->id, 'editor_id' => $editor->id,
            'action' => $edited ? 'edited' : 'approved', 'acted_at' => now(),
        ]);

        return $this->publish($story, 'manual', $editor);
    }

    public function reject(Story $story, Admin $editor, string $reason): Story
    {
        Approval::create([
            'story_id' => $story->id, 'editor_id' => $editor->id,
            'action' => 'rejected', 'reason' => $reason, 'acted_at' => now(),
        ]);
        $story->update(['status' => 'rejected']);

        return $story;
    }

    /** One-click unpublish: the story disappears from the site (status "removed"). */
    public function unpublish(Story $story): Story
    {
        $story->update(['status' => 'removed']);
        PageCache::flush();

        return $story;
    }
}
