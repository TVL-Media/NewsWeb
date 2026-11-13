<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReelResource;
use App\Models\Like;
use App\Models\Reel;
use App\Models\ReelView;
use App\Models\Save;
use App\Models\Share;
use App\Support\ReelFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** /reels: the vertical video feed, single-reel pages and the player's AJAX endpoints. */
class ReelController extends Controller
{
    public const PER_PAGE = 8;

    // GET /reels   (?topic=page-slug &category=slug narrow the feed)
    public function index(Request $request)
    {
        return $this->feedView($request, null);
    }

    // GET /reels/{slug} — the feed, starting at this reel
    public function show(Request $request, Reel $reel)
    {
        abort_unless($reel->status === 'published' && $reel->published_at?->isPast(), 404);

        return $this->feedView($request, $reel);
    }

    // GET /reels/feed?page=2&topic=&category=&exclude= — more reels for the player (JSON)
    public function feed(Request $request): JsonResponse
    {
        $page = ReelFeed::query($request->user(), null, $this->filters($request))->simplePaginate(self::PER_PAGE)->withQueryString();

        return response()->json([
            'reels' => ReelResource::collection(ReelFeed::decorate($page->getCollection(), $request->user()))->resolve($request),
            'next' => $page->nextPageUrl(),
        ]);
    }

    // GET /reels/{reel}/data — one reel (the player's हिं/EN switch)
    public function data(Request $request, Reel $reel): JsonResponse
    {
        abort_unless($reel->status === 'published', 404);

        return response()->json(['reel' => (new ReelResource(ReelFeed::decorate(collect([$reel->load(['page', 'story'])]), $request->user())->first()))->resolve($request)]);
    }

    // POST /reels/{reel}/view  { watched_seconds, completed } — guests too
    public function view(Request $request, Reel $reel): JsonResponse
    {
        $data = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'completed' => ['nullable', 'boolean'],
        ]);

        ReelView::create([
            'reel_id' => $reel->id,
            'user_id' => $request->user()?->id,
            'watched_seconds' => min($data['watched_seconds'], 3600),
            'completed' => (bool) ($data['completed'] ?? false),
        ]);
        $reel->increment('views_count');

        return response()->json(['views' => $reel->views_count]);
    }

    // POST / DELETE /reels/{reel}/like
    public function like(Request $request, Reel $reel): JsonResponse
    {
        $attrs = ['user_id' => $request->user()->id, 'likeable_type' => Reel::class, 'likeable_id' => $reel->id];

        $liked = DB::transaction(function () use ($request, $reel, $attrs) {
            if ($request->isMethod('DELETE')) {
                if (Like::where($attrs)->delete()) {
                    Reel::whereKey($reel->id)->where('likes_count', '>', 0)->decrement('likes_count');
                }

                return false;
            }
            if (Like::firstOrCreate($attrs)->wasRecentlyCreated) {
                Reel::whereKey($reel->id)->increment('likes_count');
            }

            return true;
        });

        return response()->json(['liked' => $liked, 'likes' => $reel->fresh()->likes_count]);
    }

    // POST / DELETE /reels/{reel}/save
    public function save(Request $request, Reel $reel): JsonResponse
    {
        $where = ['user_id' => $request->user()->id, 'reel_id' => $reel->id];
        $request->isMethod('DELETE') ? Save::where($where)->delete() : Save::firstOrCreate($where);

        return response()->json(['saved' => ! $request->isMethod('DELETE')]);
    }

    // POST /reels/{reel}/share  { channel } — guests too (shares are counted, not gated)
    public function share(Request $request, Reel $reel): JsonResponse
    {
        $data = $request->validate(['channel' => ['nullable', Rule::in(['whatsapp', 'link', 'native'])]]);

        Share::create(['user_id' => $request->user()?->id, 'reel_id' => $reel->id, 'channel' => $data['channel'] ?? 'link']);
        $reel->increment('shares_count');

        return response()->json(['url' => route('reels.show', $reel), 'shares' => $reel->shares_count]);
    }

    private function feedView(Request $request, ?Reel $start)
    {
        $filters = $this->filters($request) + ['exclude' => $start?->id];
        $page = ReelFeed::query($request->user(), null, $filters)->simplePaginate(self::PER_PAGE);

        $reels = $page->getCollection();
        if ($start) {
            $reels->prepend($start->load(['page', 'story']));
        }
        $reels = ReelFeed::decorate($reels, $request->user());

        $next = $page->nextPageUrl()
            ? route('reels.feed', array_filter(['page' => 2, ...$filters]))
            : null;

        return view('reels.index', [
            'reels' => ReelResource::collection($reels)->resolve($request),
            'next' => $next,
            'start' => $start,
            'description' => $start ? $this->description($start) : null,
        ]);
    }

    private function filters(Request $request): array
    {
        return array_filter([
            'topic' => $request->string('topic')->toString() ?: null,
            'category' => $request->string('category')->toString() ?: null,
            'exclude' => $request->integer('exclude') ?: null,
        ]);
    }

    private function description(Reel $reel): string
    {
        $text = $reel->story?->version($reel->language)?->card_text ?? $reel->title;

        return Str::limit($text, 160);
    }
}
