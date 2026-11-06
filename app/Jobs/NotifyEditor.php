<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Story;
use App\Notifications\StoryNeedsApproval;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification as Notifier;
use Illuminate\Support\Str;

/** A story is waiting in the approval queue: Filament bell notice + email to editors (WhatsApp later). */
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

        $editors = Admin::all();
        $headline = $story->version('en')?->headline ?? 'Untitled draft';
        $why = implode(' · ', $story->aiCheck?->reasons ?? []);
        $url = route('filament.admin.resources.approval-queues.view', $story);

        Notification::make()
            ->title('Needs approval: '.Str::limit($headline, 70))
            ->body($why ?: 'Waiting for an editor.')
            ->warning()
            ->icon('heroicon-o-shield-exclamation')
            ->actions([Action::make('review')->label('Review')->url($url)->markAsRead()])
            ->sendToDatabase($editors);

        Notifier::send($editors, new StoryNeedsApproval($headline, $why, $url));
    }
}
