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
        $trending = Story::published()
            ->with('versions')
            ->where('published_at', '>=', now()->subDays(3))
            ->orderByRaw('(likes_count + shares_count * 2 + comments_count * 3 + importance * 40) desc')
            ->limit($limit)
            ->get();

        // A quiet news day still gets a full list ("Top 5"): top up with the latest important stories.
        if ($trending->count() < $limit) {
            $trending = $trending->concat(Story::published()->with('versions')
                ->whereNotIn('id', $trending->pluck('id'))
                ->orderByDesc('published_at')->orderByDesc('importance')
                ->limit($limit - $trending->count())->get());
        }

        return $trending;
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

    public static function streakDays(?User $user): int
    {
        return (int) ($user?->streak?->current ?? 0);
    }
}
