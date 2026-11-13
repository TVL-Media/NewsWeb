<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Reel;
use App\Notifications\ReelNeedsAttention;
use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;

/**
 * Same as NotifyEditor for stories: a reel waits for approval (sensitive story or upload) or failed
 * to process → bell notice in the admin panel + email to staff who can manage reels.
 */
class NotifyReelEditors implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $reelId) {}

    public function handle(): void
    {
        $reel = Reel::with(['story.aiCheck'])->find($this->reelId);
        if (! $reel || ! in_array($reel->status, ['pending_approval', 'failed'], true)) {
            return;
        }

        $editors = Admin::where('status', 'active')->get()->filter(fn (Admin $a) => $a->can('reels.manage'))->values();
        $failed = $reel->status === 'failed';
        $title = ($failed ? 'Reel failed: ' : 'Reel needs approval: ').Str::limit($reel->title, 70);
        $why = $failed
            ? Str::limit((string) $reel->error_message, 200)
            : ($reel->source_type === 'upload' ? 'Uploaded video.' : 'Sensitive story: '.implode(' · ', (array) ($reel->story?->aiCheck?->sensitive_reasons ?: $reel->story?->aiCheck?->reasons ?: ['needs a check'])).'.');
        $url = route('admin.reels.show', $reel);

        foreach ($editors as $a) {
            Notify::admin($a->id, 'review', $title, $why, $url);
        }

        Notifier::send($editors, new ReelNeedsAttention($title, $why, $url, $failed));
    }
}
