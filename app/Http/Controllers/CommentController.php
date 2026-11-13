<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Reel;
use App\Models\Story;
use App\Support\Lang;
use App\Support\ProfanityFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** GET|POST /stories/{story}/comments and /reels/{reel}/comments */
class CommentController extends Controller
{
    public const NEW_ACCOUNT_DAILY_LIMIT = 3;

    public function index(Request $request, Story $story): JsonResponse
    {
        return $this->list($request, $story);
    }

    public function indexReel(Request $request, Reel $reel): JsonResponse
    {
        abort_unless($reel->isVisible(), 404);

        return $this->list($request, $reel);
    }

    public function storeReel(Request $request, Reel $reel): JsonResponse
    {
        abort_unless($reel->isVisible(), 404);

        return $this->create($request, $reel);
    }

    private function list(Request $request, Story|Reel $owner): JsonResponse
    {
        $comments = $owner->comments()->visible()->whereNull('parent_id')
            ->with(['user', 'replies' => fn ($q) => $q->visible()->with('user')->orderBy('id')])
            ->orderByDesc('id')
            ->cursorPaginate(20);

        $liked = $this->likedIds($request, $comments->getCollection()->flatMap(fn ($c) => [$c, ...$c->replies]));

        return response()->json([
            'comments' => $comments->getCollection()->map(fn ($c) => self::present($c, $liked))->values(),
            'next' => $comments->nextPageUrl(),
        ]);
    }

    public function store(Request $request, Story $story): JsonResponse
    {
        return $this->create($request, $story);
    }

    private function create(Request $request, Story|Reel $owner): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:1000'],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();

        if (! ProfanityFilter::isClean($data['body'])) {
            throw ValidationException::withMessages(['body' => __('Please keep it respectful. Your comment has words our community rules don\'t allow.')]);
        }

        // Accounts younger than 24 hours: at most 3 comments a day.
        if ($user->created_at->gt(now()->subDay())
            && Comment::where('user_id', $user->id)->where('created_at', '>=', now()->subDay())->count() >= self::NEW_ACCOUNT_DAILY_LIMIT) {
            throw ValidationException::withMessages(['body' => __('New accounts can post up to 3 comments a day. Please try again tomorrow.')]);
        }

        $parent = null;
        if (! empty($data['parent_id'])) {
            $parent = $owner->comments()->visible()->whereKey($data['parent_id'])->firstOrFail();
            $parent = $parent->parent_id ? $parent->parent : $parent; // replies stay one level deep
        }

        $comment = $owner->comments()->create([
            'user_id' => $user->id,
            'parent_id' => $parent?->id,
            'body' => trim($data['body']),
        ]);
        $owner->increment('comments_count');

        return response()->json(['comment' => self::present($comment->load('user')->setRelation('replies', collect()), [])], 201);
    }

    private function likedIds(Request $request, $comments): array
    {
        if (! $request->user() || $comments->isEmpty()) {
            return [];
        }

        return Like::where('user_id', $request->user()->id)
            ->where('likeable_type', Comment::class)
            ->whereIn('likeable_id', $comments->pluck('id'))
            ->pluck('likeable_id')->all();
    }

    /** Shape used by the Alpine comments box (story page) and by tests. */
    public static function present(Comment $c, array $liked): array
    {
        return [
            'id' => $c->id,
            'name' => $c->user->name ?: __('Reader'),
            'initial' => $c->user->initial(),
            'body' => $c->body,
            'ago' => Lang::ago($c->created_at),
            'likes' => (int) $c->likes_count,
            'liked' => in_array($c->id, $liked, true),
            'replies' => $c->relationLoaded('replies') ? $c->replies->map(fn ($r) => self::present($r->setRelation('replies', collect()), $liked))->values() : [],
        ];
    }
}
