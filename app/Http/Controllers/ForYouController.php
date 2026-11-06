<?php

namespace App\Http\Controllers;

use App\Models\Like;
use App\Models\ReadingEvent;
use App\Models\Save;
use App\Models\Story;
use App\Support\Rails;
use Illuminate\Http\Request;

/**
 * For You: only stories from the reader's chosen categories and followed pages, newest first,
 * skipping stories they have already read. After the last card: "You're all caught up ✓".
 */
class ForYouController extends Controller
{
    public const LIMIT = 30;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $categoryIds = $user->categories()->pluck('categories.id')->all();
        $pageIds = Rails::followedPageIds($user);
        $readIds = ReadingEvent::where('user_id', $user->id)->pluck('story_id')->unique()->all();

        $stories = Story::published()
            ->with(['versions', 'category', 'source', 'page'])
            ->where(fn ($q) => $q->whereIn('category_id', $categoryIds ?: [0])->orWhereIn('page_id', $pageIds ?: [0]))
            ->whereNotIn('id', $readIds ?: [0])
            ->where('published_at', '>=', now()->subDays(7))
            ->latest('published_at')
            ->limit(self::LIMIT)
            ->get();

        $ids = $stories->pluck('id');

        return view('for-you', [
            'stories' => $stories,
            'followed' => $pageIds,
            'liked' => Like::where('user_id', $user->id)->where('likeable_type', Story::class)->whereIn('likeable_id', $ids)->pluck('likeable_id')->all(),
            'saved' => Save::where('user_id', $user->id)->whereIn('story_id', $ids)->pluck('story_id')->all(),
            'hasTopics' => ! empty($categoryIds) || ! empty($pageIds),
        ]);
    }
}
