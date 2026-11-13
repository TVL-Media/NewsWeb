<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Like;
use App\Models\ReadingEvent;
use App\Models\Save;
use App\Models\Story;
use App\Support\Rails;
use Illuminate\Http\Request;

/**
 * For You: only stories from the reader's chosen categories and followed pages.
 * New (unread) stories first, then "You're all caught up ✓", then "Earlier": stories they
 * have already seen, newest first — so the feed never goes blank between news cycles.
 */
class ForYouController extends Controller
{
    public const LIMIT = 30;
    public const EARLIER_LIMIT = 20;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $categoryIds = $user->categories()->pluck('categories.id')->all();
        $pageIds = Rails::followedPageIds($user);
        $readIds = ReadingEvent::where('user_id', $user->id)->pluck('story_id')->unique()->all();

        // ?topic=slug: that topic's latest stories, whether or not it is one of the reader's topics.
        $topic = $request->filled('topic') ? Category::active()->where('slug', $request->string('topic'))->first() : null;

        $base = fn () => Story::published()
            ->with(['versions', 'category', 'source', 'page'])
            ->when($topic,
                fn ($q) => $q->where('category_id', $topic->id),
                fn ($q) => $q->where(fn ($q) => $q->whereIn('category_id', $categoryIds ?: [0])->orWhereIn('page_id', $pageIds ?: [0])))
            ->where('published_at', '>=', now()->subDays(7))
            ->latest('published_at');

        $new = $base()->whereNotIn('id', $readIds ?: [0])->limit(self::LIMIT)->get();
        $earlier = $readIds ? $base()->whereIn('id', $readIds)->limit(self::EARLIER_LIMIT)->get() : collect();
        $stories = $new->concat($earlier)->values();

        $ids = $stories->pluck('id');

        return view('for-you', [
            'stories' => $stories,
            'newCount' => $new->count(),
            // Sidebar: topic chips filter the feed; a red dot marks followed pages with new stories.
            'topics' => Category::active()->orderBy('sort_order')->get(),
            'topic' => $topic,
            'pages' => $user->followedPages()->orderBy('name')->get(),
            'freshPageIds' => $new->pluck('page_id')->filter()->unique()->values()->all(),
            'followed' => $pageIds,
            'liked' => Like::where('user_id', $user->id)->where('likeable_type', Story::class)->whereIn('likeable_id', $ids)->pluck('likeable_id')->all(),
            'saved' => Save::where('user_id', $user->id)->whereIn('story_id', $ids)->pluck('story_id')->all(),
            'hasTopics' => $topic || ! empty($categoryIds) || ! empty($pageIds),
        ]);
    }
}
