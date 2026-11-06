<?php

namespace App\Jobs;

use App\Models\Briefing;
use App\Services\BriefingAudio;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Joins the 5 stories' audio into the Morning 5 track (EN + HI) with chapter timestamps. */
class BuildBriefingAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $briefingId) {}

    public function handle(BriefingAudio $audio): void
    {
        if ($briefing = Briefing::find($this->briefingId)) {
            $audio->build($briefing);
        }
    }
}
