<?php

namespace App\Support;

use App\Models\Briefing;
use App\Models\Page;
use App\Models\ReadingEvent;
use App\Models\Story;
use App\Models\User;
use Illuminate\Support\Collection;

/** Data for cards that appear on several pages: Trending, Pages to follow, Morning 5, quiz streak. */
class Rails
{
    /** Most engaged stories from the last 3 days. */
    public static function trending(int $limit = 5): Collection
    {
        return Story::published()
            ->with('versions')
            ->where('published_at', '>=', now()->subDays(3))
            ->orderByRaw('(likes_count + shares_count * 2 + comments_count * 3 + importance * 40) desc')
            ->limit($limit)
            ->get();
    }

    /** Popular pages the reader doesn't follow yet. */
    public static function pagesToFollow(?User $user, int $limit = 4): Collection
    {
        return Page::query()
            ->withCount('followers')
            ->when($user, fn ($q) => $q->whereDoesntHave('followers', fn ($f) => $f->whereKey($user->id)))
            ->orderByDesc('followers_count')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** IDs of pages the user follows (empty for guests). */
    public static function followedPageIds(?User $user): array
    {
        return $user ? $user->followedPages()->pluck('pages.id')->all() : [];
    }

    /** Today's Morning 5 and how many of its stories the reader has read today. */
    public static function morning5(?User $user): array
    {
        // Before 06:00 IST (or if the job hasn't run) show the latest briefing rather than nothing.
        $briefing = Briefing::today() ?? Briefing::latest('date')->first();
        $read = 0;

        if ($briefing && $user) {
            $read = ReadingEvent::where('user_id', $user->id)
                ->whereIn('story_id', $briefing->story_ids)
                ->whereDate('read_on', now(config('app.timezone'))->toDateString())
                ->distinct()
                ->count('story_id');
        }

        return ['briefing' => $briefing, 'read' => $read];
    }

    public static function streakDays(?User $user): int
    {
        return (int) ($user?->streak?->current ?? 0);
    }
}
