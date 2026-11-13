<?php

namespace App\Support;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Quiz;
use App\Models\ReadingEvent;
use App\Models\Story;
use App\Models\User;
use App\Services\StreakService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * The reader's notifications (bell in the header, /notifications). Built from what already happens
 * on the site, so there is no notifications table to fill:
 * breaking news, new stories from followed pages, replies and likes on the reader's comments,
 * results of polls they voted in, today's quiz and a streak reminder. Guests get breaking news + quiz.
 */
class ReaderNotifications
{
    public const LIMIT = 40;

    /** @return Collection<int, array{type: string, icon: string, title: string, body: ?string, url: string, at: CarbonInterface, image: ?string}> */
    public static function for(?User $user): Collection
    {
        $items = collect();
        $tz = config('app.timezone');

        // Breaking news (last 3 days)
        Story::published()->with('versions')->where('is_breaking', true)
            ->where('published_at', '>=', now()->subDays(3))->latest('published_at')->limit(5)->get()
            ->each(fn (Story $s) => $items->push([
                'type' => 'breaking', 'icon' => 'zap', 'title' => __('Breaking news'),
                'body' => $s->version()?->headline, 'url' => route('story.show', $s), 'at' => $s->published_at, 'image' => $s->photoUrl(),
            ]));

        // Today's quiz
        $quiz = Quiz::today();
        $playable = $quiz && in_array($quiz->status, ['approved', 'published'], true);
        $done = $playable && $user && $user->quizAttempts()->where('quiz_id', $quiz->id)->whereNotNull('completed_at')->exists();
        if ($playable && ! $done) {
            $items->push([
                'type' => 'quiz', 'icon' => 'help-circle', 'title' => __('Today\'s quiz is ready'),
                'body' => __('5 questions on today\'s news. Keep your streak going.'), 'url' => route('quiz'),
                'at' => now($tz)->startOfDay()->addHours(6)->min(now()), 'image' => null,
            ]);
        }

        if (! $user) {
            return self::sorted($items);
        }

        // Streak reminder: the reader has a streak and hasn't read enough today yet.
        $streak = (int) ($user->streak?->current ?? 0);
        $readToday = ReadingEvent::where('user_id', $user->id)->whereDate('read_on', now($tz)->toDateString())->count();
        $left = StreakService::readsPerDay() - $readToday;
        if ($streak > 0 && $left > 0) {
            $items->push([
                'type' => 'streak', 'icon' => 'flame', 'title' => __('Keep your :n-day streak', ['n' => $streak]),
                'body' => trans_choice('Read :count more story today.|Read :count more stories today.', $left), 'url' => route('home'),
                'at' => now()->subMinute(), 'image' => null,
            ]);
        }

        // New stories from followed pages (last 2 days); a breaking story is already listed above.
        $followed = Rails::followedPageIds($user);
        if ($followed) {
            Story::published()->with(['versions', 'page'])->whereIn('page_id', $followed)
                ->where('is_breaking', false)
                ->where('published_at', '>=', now()->subDays(2))->latest('published_at')->limit(10)->get()
                ->each(fn (Story $s) => $items->push([
                    'type' => 'page', 'icon' => 'bell', 'title' => __('New from :page', ['page' => $s->page->name()]),
                    'body' => $s->version()?->headline, 'url' => route('story.show', $s), 'at' => $s->published_at, 'image' => $s->page->avatarUrl(),
                ]));
        }

        // Replies to the reader's comments (last 14 days)
        $mine = Comment::where('user_id', $user->id)->pluck('id');
        if ($mine->isNotEmpty()) {
            Comment::visible()->with(['user', 'parent.story', 'parent.reel'])->whereIn('parent_id', $mine)
                ->where('user_id', '!=', $user->id)->where('created_at', '>=', now()->subDays(14))
                ->latest()->limit(10)->get()
                ->each(fn (Comment $c) => $items->push([
                    'type' => 'reply', 'icon' => 'message-circle',
                    'title' => __(':name replied to your comment', ['name' => $c->user->name ?: __('A reader')]),
                    'body' => Str::limit($c->body, 120), 'url' => self::commentUrl($c->parent), 'at' => $c->created_at, 'image' => null,
                ]));

            // Likes on the reader's comments, one item per comment (last 14 days)
            Like::where('likeable_type', Comment::class)->whereIn('likeable_id', $mine)
                ->where('user_id', '!=', $user->id)->where('created_at', '>=', now()->subDays(14))
                ->get()->groupBy('likeable_id')
                ->each(function ($likes, $commentId) use ($items) {
                    $comment = Comment::with(['story', 'reel'])->find($commentId);
                    if (! $comment) {
                        return;
                    }
                    $items->push([
                        'type' => 'like', 'icon' => 'heart',
                        'title' => trans_choice(':count person liked your comment|:count people liked your comment', $likes->count()),
                        'body' => Str::limit($comment->body, 120), 'url' => self::commentUrl($comment), 'at' => $likes->max('created_at'), 'image' => null,
                    ]);
                });
        }

        // Results of polls the reader voted in that have closed (last 7 days)
        $voted = PollVote::where('user_id', $user->id)->pluck('poll_id');
        if ($voted->isNotEmpty()) {
            Poll::with('story')->whereIn('id', $voted)->where('status', 'closed')
                ->where('ends_at', '>=', now()->subDays(7))->latest('ends_at')->limit(5)->get()
                ->each(fn (Poll $p) => $items->push([
                    'type' => 'poll', 'icon' => 'trending-up', 'title' => __('Poll results are in'),
                    'body' => $p->question, 'url' => $p->story ? route('story.show', $p->story) : route('briefing'), 'at' => $p->ends_at, 'image' => null,
                ]));
        }

        return self::sorted($items);
    }

    /** Is there anything newer than the last time the reader opened /notifications? (the red dot) */
    public static function hasUnseen(?User $user, ?int $seenAt): bool
    {
        // Every page shows the bell, so the newest item's time is cached briefly.
        $latest = Cache::remember('notifications.latest.'.($user?->id ?? 'guest'), 120,
            fn () => self::for($user)->first()['at']?->getTimestamp() ?? 0);

        return $latest > 0 && (! $seenAt || $latest > $seenAt);
    }

    private static function sorted(Collection $items): Collection
    {
        return $items->sortByDesc(fn ($i) => $i['at']->getTimestamp())->take(self::LIMIT)->values();
    }

    private static function commentUrl(?Comment $comment): string
    {
        return match (true) {
            $comment?->story !== null => route('story.show', $comment->story).'#comments-title',
            $comment?->reel !== null => route('reels.show', $comment->reel),
            default => route('home'),
        };
    }
}
