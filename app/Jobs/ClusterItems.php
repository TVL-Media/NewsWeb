<?php

namespace App\Jobs;

use App\Services\Newsroom\Clusterer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Groups new feed items by event; every new cluster gets an AI draft. */
class ClusterItems implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 120;

    public function handle(Clusterer $clusterer): void
    {
        foreach ($clusterer->run() as $cluster) {
            // Short delay so reports arriving minutes apart land in the same cluster first.
            GenerateDrafts::dispatch($cluster->id)->delay(now()->addMinutes(config('queue.default') === 'sync' ? 0 : 2));
        }
    }
}
