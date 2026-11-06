<?php

namespace App\Jobs;

use App\Models\AiCheck;
use App\Models\Story;
use App\Services\Newsroom\StoryChecks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Fact, copy, confidence and sensitivity checks on a draft; records an ai_checks row, then routes it. */
class RunChecks implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storyId, public array $riskFlags = [], public float $aiConfidence = 0.0) {}

    public function handle(StoryChecks $checks): void
    {
        $story = Story::with(['versions', 'category', 'cluster.items.feedSource'])->find($this->storyId);
        if (! $story || $story->status !== 'draft') {
            return;
        }

        $sources = $story->cluster?->items ?? collect();
        $trusted = $sources->isNotEmpty() && $sources->every(fn ($i) => (bool) $i->feedSource?->trusted);

        $result = $checks->run($story, $sources, $this->riskFlags, $this->aiConfidence, $trusted);

        AiCheck::create([
            'story_id' => $story->id,
            'risk_level' => $result['risk_level'],
            'sensitive_reasons' => $result['sensitive_reasons'],
            'risk_flags' => $this->riskFlags,
            'confidence' => $result['confidence'],
            'fact_mismatches' => $result['fact_mismatches'],
            'copy_score' => $result['copy_score'],
            'decision' => $result['passed'] ? 'auto' : 'approval',
            'reasons' => $result['reasons'],
        ]);

        RouteStory::dispatch($story->id);
    }
}
