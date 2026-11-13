<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController;
use App\Http\Resources\ReelResource;
use App\Models\Reel;
use App\Support\ReelFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * /api/v1/reels for the Android app. Same feed and JSON shape as the website player.
 * Likes, saves, shares, views and comments reuse the website controllers (they answer JSON).
 */
class ReelController extends Controller
{
    public const PER_PAGE = 10;

    // GET /api/v1/reels?cursor=&category=&lang=&limit=
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cursor' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:80'],
            'lang' => ['nullable', Rule::in(['en', 'hi'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
        $limit = $data['limit'] ?? self::PER_PAGE;
        $offset = $this->decodeCursor($data['cursor'] ?? null);

        // The feed is ranked (followed pages, day, views), so the cursor is an opaque position in it.
        $rows = ReelFeed::query($request->user(), $data['lang'] ?? null, ['category' => $data['category'] ?? null])
            ->offset($offset)->limit($limit + 1)->get();
        $more = $rows->count() > $limit;
        $reels = ReelFeed::decorate($rows->take($limit), $request->user());

        return response()->json([
            'data' => ReelResource::collection($reels)->resolve($request),
            'meta' => ['next_cursor' => $more ? $this->encodeCursor($offset + $limit) : null],
        ]);
    }

    // GET /api/v1/reels/{id}
    public function show(Request $request, Reel $reel): JsonResponse
    {
        abort_unless($reel->isVisible(), 404);
        $reel->load(['page', 'story']);

        return response()->json(['data' => (new ReelResource(ReelFeed::decorate(collect([$reel]), $request->user())->first()))->resolve($request)]);
    }

    // POST /api/v1/reels/{id}/report  { reason }
    public function report(Request $request, Reel $reel): JsonResponse
    {
        abort_unless($reel->isVisible(), 404);
        $request->merge(['target_type' => 'reel', 'target_id' => $reel->id]);

        return app(ReportController::class)->store($request);
    }

    private function encodeCursor(int $offset): string
    {
        return rtrim(strtr(base64_encode(json_encode(['o' => $offset])), '+/', '-_'), '=');
    }

    private function decodeCursor(?string $cursor): int
    {
        if (! $cursor) {
            return 0;
        }
        $data = json_decode((string) base64_decode(strtr($cursor, '-_', '+/')), true);
        abort_unless(is_array($data) && is_int($data['o'] ?? null) && $data['o'] >= 0 && $data['o'] <= 10000, 422, 'Invalid cursor.');

        return $data['o'];
    }
}
