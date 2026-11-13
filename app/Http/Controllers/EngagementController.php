<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Page;
use App\Models\Reaction;
use App\Models\ReadingEvent;
use App\Models\Save;
use App\Models\Share;
use App\Models\Story;
use App\Services\ShareImage;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** AJAX endpoints behind the optimistic UI. All return JSON with the new state. */
class EngagementController extends Controller
{
    // POST / DELETE /stories/{story}/like
    public function like(Request $request, Story $story): JsonResponse
    {
        $liked = $this->toggleLike($request, $story);
        $story->refresh();

        return response()->json(['liked' => $liked, 'likes' => $story->likes_count]);
    }

    // POST / DELETE /comments/{comment}/like
    public function likeComment(Request $request, Comment $comment): JsonResponse
    {
        $liked = $this->toggleLike($request, $comment);
        $comment->refresh();

        return response()->json(['liked' => $liked, 'likes' => $comment->likes_count]);
    }

    private function toggleLike(Request $request, Story|Comment $model): bool
    {
        $attrs = ['user_id' => $request->user()->id, 'likeable_type' => $model::class, 'likeable_id' => $model->id];

        return DB::transaction(function () use ($request, $model, $attrs) {
            if ($request->isMethod('DELETE')) {
                if (Like::where($attrs)->delete()) {
                    $model->newQuery()->whereKey($model->id)->where('likes_count', '>', 0)->decrement('likes_count');
                }

                return false;
            }

            $like = Like::firstOrCreate($attrs);
            if ($like->wasRecentlyCreated) {
                $model->newQuery()->whereKey($model->id)->increment('likes_count');
            }

            return true;
        });
    }

    // PUT /stories/{story}/reaction   { type: surprising|unfair|need_info|good_news|null }
    public function reaction(Request $request, Story $story): JsonResponse
    {
        $data = $request->validate(['type' => ['nullable', Rule::in(Reaction::TYPES)]]);
        $where = ['user_id' => $request->user()->id, 'story_id' => $story->id];

        if (empty($data['type'])) {
            Reaction::where($where)->delete();
        } else {
            Reaction::updateOrCreate($where, ['type' => $data['type']]);
        }

        return response()->json(['reaction' => $data['type'] ?? null]);
    }

    // POST / DELETE /stories/{story}/save
    public function save(Request $request, Story $story): JsonResponse
    {
        $where = ['user_id' => $request->user()->id, 'story_id' => $story->id];

        if ($request->isMethod('DELETE')) {
            Save::where($where)->delete();
        } else {
            Save::firstOrCreate($where);
        }

        return response()->json(['saved' => ! $request->isMethod('DELETE')]);
    }

    // POST /stories/{story}/share  { channel, lang } → share image + link
    public function share(Request $request, Story $story, ShareImage $images): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['nullable', Rule::in(['image', 'whatsapp', 'link', 'native'])],
            'lang' => ['nullable', Rule::in(['en', 'hi'])],
        ]);

        Share::create(['user_id' => $request->user()->id, 'story_id' => $story->id, 'channel' => $data['channel'] ?? 'link']);
        $story->increment('shares_count');

        return response()->json([
            'url' => route('story.show', $story),
            'image_url' => $images->urlFor($story, $data['lang'] ?? app()->getLocale()),
        ]);
    }

    // POST /stories/{story}/read — one reading event per story per IST day (used for streaks).
    public function read(Request $request, Story $story, StreakService $streaks): JsonResponse
    {
        $today = now(config('app.timezone'))->toDateString();

        // The unique (user, story, read_on) index makes this idempotent.
        ReadingEvent::query()->insertOrIgnore([
            'user_id' => $request->user()->id, 'story_id' => $story->id, 'read_on' => $today, 'read_at' => now(),
        ]);

        $readToday = ReadingEvent::where('user_id', $request->user()->id)->whereDate('read_on', $today)->count();

        // 3 reads in an IST day count toward the streak.
        [$streak, $increased] = $readToday >= StreakService::readsPerDay()
            ? $streaks->recordActivity($request->user())
            : [null, false];

        return response()->json([
            'read_today' => $readToday,
            'streak' => $streak?->current,
            'streak_increased' => $increased,
        ]);
    }

    // POST / DELETE /pages/{page}/follow
    public function follow(Request $request, Page $page): JsonResponse
    {
        if ($request->isMethod('DELETE')) {
            $request->user()->followedPages()->detach($page->id);
        } else {
            $request->user()->followedPages()->syncWithoutDetaching([$page->id]);
        }

        return response()->json([
            'following' => ! $request->isMethod('DELETE'),
            'followers' => $page->followers()->count(),
        ]);
    }
}
