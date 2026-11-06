<?php

namespace App\Jobs;

use App\Models\Poll;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Every 15 minutes: live polls past ends_at become closed. */
class ClosePolls implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Poll::where('status', 'live')->whereNotNull('ends_at')->where('ends_at', '<=', now())->update(['status' => 'closed']);
    }
}
