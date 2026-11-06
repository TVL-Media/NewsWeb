<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Like;
use App\Models\Poll;
use App\Models\Reaction;
use App\Models\Save;
use App\Models\Story;
use App\Support\Rails;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoryController extends Controller
{
    public function show(Request $request, Story $story)
    {
        abort_unless(in_array($story->status, ['published', 'corrected'], true) && $story->published_at?->isPast(), 404);

        $story->load(['versions', 'category', 'source', 'page' => fn ($q) => $q->withCount('followers')]);
        $user = $request->user();

        $related = Story::published()
            ->with(['versions', 'category'])
            ->whereKeyNot($story->id)
            ->where(fn ($q) => $q->where('category_id', $story->category_id)->orWhere('page_id', $story->page_id))
            ->latest('published_at')
            ->limit(4)
            ->get();

        $poll = Poll::with('options')->where('story_id', $story->id)->whereIn('status', ['live', 'closed'])->latest()->first()
            ?? Poll::live()->with('options')->whereNull('created_by')->latest()->first();

        $comments = $story->comments()->visible()->whereNull('parent_id')
            ->with(['user', 'replies' => fn ($q) => $q->visible()->with('user')->orderBy('id')])
            ->orderByDesc('id')->limit(20)->get();
        $likedComments = $user
            ? Like::where('user_id', $user->id)->where('likeable_type', Comment::class)
                ->whereIn('likeable_id', $comments->flatMap(fn ($c) => [$c, ...$c->replies])->pluck('id'))
                ->pluck('likeable_id')->all()
            : [];

        $desk = $story->page?->desk_label
            ? Str::of($story->page->desk_label)->replace(' DESK', '')->lower()->title()->toString()
            : $story->category?->name();

        return view('story.show', [
            'story' => $story,
            'versions' => ['en' => $story->version('en'), 'hi' => $story->version('hi')],
            'desk' => $desk,
            'related' => $related,
            'poll' => $poll,
            'commentsData' => $comments->map(fn ($c) => CommentController::present($c, $likedComments))->values(),
            'morning5' => Rails::morning5($user),
            'following' => $story->page && in_array($story->page_id, Rails::followedPageIds($user), true),
            'liked' => $user ? Like::where(['user_id' => $user->id, 'likeable_type' => Story::class, 'likeable_id' => $story->id])->exists() : false,
            'saved' => $user ? Save::where(['user_id' => $user->id, 'story_id' => $story->id])->exists() : false,
            'reaction' => $user ? Reaction::where(['user_id' => $user->id, 'story_id' => $story->id])->value('type') : null,
            'votedOptionId' => $user && $poll ? $poll->votes()->where('user_id', $user->id)->value('poll_option_id') : null,
        ]);
    }
}
