<?php

namespace App\Jobs;

use App\Models\Reel;
use App\Models\Story;
use App\Services\Reels\ReelBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Auto reel for one published story in one language (queued from StoryPublished).
 * Skips when the reel already matches the story's current headline/key points/photo, unless forced
 * (Re-generate). Low-risk story → published; sensitive story → approval queue.
 */
class GenerateReel implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $storyId, public string $language, public bool $force = false) {}

    public function handle(ReelBuilder $builder): void
    {
        $story = Story::with(['versions', 'category', 'aiCheck'])->find($this->storyId);
        if (! $story || ! in_array($story->status, ['published', 'corrected'], true)) {
            return;
        }

        $version = $story->versions->firstWhere('language', $this->language);
        if (! $version) {
            return;
        }

        $hash = ReelBuilder::contentHash($story, $version);
        $reel = Reel::firstOrNew(['story_id' => $story->id, 'language' => $this->language, 'source_type' => 'auto']);

        if ($reel->exists && ! $this->force && $reel->source_hash === $hash && $reel->status !== 'failed') {
            return;
        }

        // An editor took this reel off the site (reason kept until it is approved again):
        // a rebuild goes back to the queue, never straight live.
        $editorRejected = $reel->exists && $reel->rejection_reason !== null;

        $reel->fill([
            'page_id' => $story->page_id,
            'category_id' => $story->category_id,
            'title' => Str::limit($version->headline, 250, ''),
            'status' => 'processing',
            'error_message' => null,
        ]);
        $reel->slug ??= $this->slug($story);
        $reel->save();

        $old = array_filter($reel->only(['video_720_path', 'video_480_path', 'thumbnail_path']));

        try {
            $files = $builder->fromStory($reel, $story, $version);
        } catch (Throwable $e) {
            $reel->markFailed($e->getMessage());
            report($e);
            NotifyReelEditors::dispatch($reel->id);

            return;
        }

        $review = $editorRejected || $story->aiCheck?->risk_level === 'sensitive';
        $reel->update([
            ...$files,
            'source_hash' => $hash,
            'status' => $review ? 'pending_approval' : 'published',
            'published_at' => $review ? null : ($reel->published_at ?? now()),
        ]);

        $builder->deleteFiles($old);

        if ($review) {
            NotifyReelEditors::dispatch($reel->id);
        }
    }

    public function failed(?Throwable $e): void
    {
        Reel::where(['story_id' => $this->storyId, 'language' => $this->language, 'source_type' => 'auto', 'status' => 'processing'])
            ->first()?->markFailed($e?->getMessage() ?? 'The reel job stopped before it finished.');
    }

    private function slug(Story $story): string
    {
        $slug = Str::limit($story->slug, 200, '').($this->language === 'hi' ? '-hi' : '').'-reel';

        return Reel::where('slug', $slug)->exists() ? $slug.'-'.Str::lower(Str::random(5)) : $slug;
    }
}
