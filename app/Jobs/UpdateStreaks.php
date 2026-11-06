<?php

namespace App\Jobs;

use App\Models\Streak;
use App\Services\StreakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** 00:05 IST: for every active streak, spend a freeze on a missed day or reset it. */
class UpdateStreaks implements ShouldQueue
{
    use Queueable;

    public function handle(StreakService $streaks): void
    {
        Streak::where('current', '>', 0)->chunkById(500, function ($chunk) use ($streaks) {
            foreach ($chunk as $streak) {
                $streaks->closeDay($streak);
            }
        });
    }
}
