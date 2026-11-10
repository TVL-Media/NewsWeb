<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PageStory;
use App\Models\Poll;
use App\Models\Story;
use App\Support\Lang;
use App\Support\Rails;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    private const PER_PAGE = 12;

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $category = $request->filled('category')
            ? Category::where('slug', $request->string('category'))->first()
            : null;

        $base = fn () => Story::published()
            ->with(['versions', 'category', 'source'])
            ->when($category, fn ($q) => $q->where('category_id', $category->id));

        // Hero = most important story of the last 24 hours (or the newest, if none).
        $hero = $base()->where('published_at', '>=', now()->subDay())
            ->orderByDesc('importance')->latest('published_at')->first()
            ?? $base()->latest('published_at')->first();

        $latest = $base()
            ->when($hero, fn ($q) => $q->whereKeyNot($hero->id))
            ->latest('published_at')
            ->simplePaginate(self::PER_PAGE)
            ->withQueryString();

        // "Load more" fetches the next page as an HTML fragment.
        if ($request->boolean('fragment')) {
            return response()->view('partials.latest-cards', ['stories' => $latest])
                ->header('X-Next-Page', $latest->nextPageUrl() ? $latest->nextPageUrl().'&fragment=1' : '');
        }

        $followed = Rails::followedPageIds($user);

        return view('home', [
            'greeting' => $user ? Lang::greeting().($user->name ? ', '.$user->firstName() : '') : __('Today\'s top news'),
            'today' => now(config('app.timezone')),
            'activeChip' => $category?->slug ?? 'all',
            'hero' => $hero,
            'latest' => $latest,
            'circles' => $this->circles($user, $followed),
            'morning5' => Rails::morning5($user),
            'streak' => Rails::streakDays($user),
            'trending' => Rails::trending(),
            'pagesToFollow' => Rails::pagesToFollow($user, 4),
            'followed' => $followed,
            // Newest live editorial poll; on a category page, one set to that category (admin → Polls) comes first.
            'poll' => Poll::live()->with('options')->whereNull('created_by')
                ->when($category, fn ($q) => $q->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$category->id]))
                ->latest()->first(),
        ]);
    }

    public const MAX_CIRCLES = 8;

    /**
     * Story circles after the red "5":
     * - signed-in readers: 24-hour stories from pages they follow (opened in the full-screen viewer),
     *   then their chosen topics as category circles, so the row is never empty
     * - guests: top categories
     */
    private function circles($user, array $followed): array
    {
        $pageStories = ! $user ? collect() : PageStory::active()
            ->with(['page', 'story.versions'])
            ->whereIn('page_id', $followed)
            ->orderBy('expires_at')
            ->get()
            ->groupBy('page_id');

        $viewer = $pageStories->map(fn ($items) => [
            'page' => $items->first()->page->name(),
            'avatar' => $items->first()->page->avatarUrl(),
            'slides' => $items->map(fn (PageStory $ps) => [
                'image' => $ps->imageUrl(),
                'headline' => $ps->story?->version()?->headline,
                'url' => $ps->story ? route('story.show', $ps->story) : route('page.show', $ps->page),
                'ago' => Lang::ago($ps->created_at),
            ])->values(),
        ])->values();

        $room = max(0, self::MAX_CIRCLES - $viewer->count());
        $chosen = $user ? $user->categories()->orderBy('sort_order')->limit($room)->get() : collect();
        $categories = $chosen->isNotEmpty()
            ? $chosen
            : Category::active()->orderBy('sort_order')->limit(min(6, $room))->get();

        return ['viewer' => $viewer, 'categories' => $categories];
    }
}
