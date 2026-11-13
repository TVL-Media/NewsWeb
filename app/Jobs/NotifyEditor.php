<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Story;
use App\Notifications\StoryNeedsApproval;
use App\Support\Notify;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;

/** A story is waiting in the approval queue: bell notice in the admin panel + email to editors (WhatsApp later). */
class NotifyEditor implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $storyId) {}

    public function handle(): void
    {
        $story = Story::with(['versions', 'aiCheck'])->find($this->storyId);
        if (! $story) {
            return;
        }

        // Staff who can decide on reviews: bell notice in the admin panel + email.
        $editors = Admin::where('status', 'active')->get()->filter(fn (Admin $a) => $a->can('review.decide'))->values();
        $headline = $story->version('en')?->headline ?? 'Untitled draft';
        $why = implode(' · ', $story->aiCheck?->reasons ?? []);
        $url = route('admin.review.show', $story);

        foreach ($editors as $a) {
            Notify::admin($a->id, 'review', 'Needs approval: '.Str::limit($headline, 70), $why ?: 'Waiting for an editor.', $url);
        }

        Notifier::send($editors, new StoryNeedsApproval($headline, $why, $url));
    }
}
