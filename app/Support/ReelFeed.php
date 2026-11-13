<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Like;
use App\Models\Page;
use App\Models\Reel;
use App\Models\Save;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The reels feed, shared by /reels, Home, topic pages and (Milestone 2) the API.
 * Order: followed pages first (signed-in readers), then newest day first, most watched within a day.
 * Language: one reel per story in the reader's language, falling back to the other language when
 * a story only has the other one. Uploaded reels (no story) are always included.
 */
class ReelFeed
{
    public static function query(?User $user, ?string $lang = null, array $filters = []): Builder
    {
        $lang ??= Lang::current();
        $followed = Rails::followedPageIds($user);

        return Reel::published()
            ->with(['page', 'story'])
            ->where(function (Builder $q) use ($lang) {
                $q->where('language', $lang)
                    ->orWhereNull('story_id')
                    ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('reels as same')
                        ->whereColumn('same.story_id', 'reels.story_id')
                        ->where('same.language', $lang)
                        ->where('same.status', 'published'));
            })
            ->when($filters['category'] ?? null, fn ($q, $slug) => $q->whereIn('category_id', Category::where('slug', $slug)->select('id')))
            ->when($filters['topic'] ?? null, fn ($q, $slug) => $q->whereIn('page_id', Page::where('slug', $slug)->select('id')))
            ->when($filters['exclude'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            ->when($followed, fn ($q) => $q->orderByRaw('CASE WHEN page_id IN ('.implode(',', array_map('intval', $followed)).') THEN 0 ELSE 1 END'))
            ->orderByRaw('DATE(published_at) DESC')
            ->orderByDesc('views_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id');
    }

    /**
     * Adds what the player needs to each reel, in a few batched queries:
     * is_liked, is_saved, page_following, other_language_reel_id.
     */
    public static function decorate(Collection $reels, ?User $user): Collection
    {
        if ($reels->isEmpty()) {
            return $reels;
        }

        $ids = $reels->pluck('id');
        $liked = $user ? Like::where(['user_id' => $user->id, 'likeable_type' => Reel::class])->whereIn('likeable_id', $ids)->pluck('likeable_id')->all() : [];
        $saved = $user ? Save::where('user_id', $user->id)->whereIn('reel_id', $ids)->pluck('reel_id')->all() : [];
        $followed = Rails::followedPageIds($user);

        $others = Reel::published()
            ->whereIn('story_id', $reels->pluck('story_id')->filter())
            ->get(['id', 'story_id', 'language'])
            ->groupBy('story_id');

        return $reels->each(function (Reel $reel) use ($liked, $saved, $followed, $others) {
            $reel->is_liked = in_array($reel->id, $liked, true);
            $reel->is_saved = in_array($reel->id, $saved, true);
            $reel->page_following = $reel->page_id && in_array($reel->page_id, $followed, true);
            $reel->other_language_reel_id = $reel->story_id
                ? $others->get($reel->story_id)?->firstWhere('language', '!=', $reel->language)?->id
                : null;
        });
    }
}
