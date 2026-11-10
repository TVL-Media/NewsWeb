<?php

namespace App\Jobs;

use App\Models\TranslationJob;
use App\Services\Translation\TranslationManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Runs one AI translation (admin panel → Translate). */
class TranslateStory implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $jobId) {}

    public function handle(TranslationManager $tm): void
    {
        $job = TranslationJob::with('story')->find($this->jobId);
        if ($job && $job->story && in_array($job->status, ['queued', 'failed'], true)) {
            $tm->process($job);
        }
    }
}
