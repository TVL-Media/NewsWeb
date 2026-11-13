<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\PageStory;
use App\Models\Poll;
use App\Models\Story;
use App\Support\Lang;
use App\Support\Rails;
use App\Support\ReelFeed;
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
            'streak' => Rails::streakDays($user),
            'trending' => Rails::trending(),
            'pagesToFollow' => Rails::pagesToFollow($user, 4),
            'followed' => $followed,
            // Newest live editorial poll; on a category page, one set to that category (admin → Polls) comes first.
            'poll' => Poll::live()->with('options')->whereNull('created_by')
                ->when($category, fn ($q) => $q->orderByRaw('CASE WHEN category_id = ? THEN 0 ELSE 1 END', [$category->id]))
                ->latest()->first(),
            'reels' => config('reels.enabled')
                ? ReelFeed::query($user, null, ['category' => $category?->slug])->limit(10)->get()
                : collect(),
        ]);
    }

    public const MAX_CIRCLES = 8;

    public const CATEGORY_SLIDES = 6;

    /**
     * Story circles. Every circle opens the full-screen viewer (Instagram-style); when one circle's
     * stories end, the next circle's start. Order:
     * - "Top 5": the five trending stories
     * - signed-in readers: 24-hour stories from pages they follow
     * - topics (the reader's chosen ones, or the top categories for guests): each topic's latest stories
     * Topics without a story in the last 3 days get no circle.
     *
     * @return list<array{kind: string, label: string, avatar: ?string, badge: ?string, slides: list<array>}>
     */
    private function circles($user, array $followed): array
    {
        $slide = fn (Story $s) => [
            'image' => $s->photoUrl(),
            'headline' => $s->version()?->headline,
            'url' => route('story.show', $s),
            'ago' => $s->ago(),
        ];

        $groups = [[
            'kind' => 'top5', 'label' => __('Top 5'), 'avatar' => null, 'badge' => '5',
            'slides' => Rails::trending()->map($slide)->values()->all(),
        ]];

        if ($user) {
            $pageStories = PageStory::active()
                ->with(['page', 'story.versions'])
                ->whereIn('page_id', $followed)
                ->orderBy('expires_at')
                ->get()
                ->groupBy('page_id');

            foreach ($pageStories as $items) {
                $groups[] = [
                    'kind' => 'page', 'label' => $items->first()->page->name(), 'avatar' => $items->first()->page->avatarUrl(), 'badge' => null,
                    'slides' => $items->map(fn (PageStory $ps) => [
                        'image' => $ps->imageUrl(),
                        'headline' => $ps->story?->version()?->headline,
                        'url' => $ps->story ? route('story.show', $ps->story) : route('page.show', $ps->page),
                        'ago' => Lang::ago($ps->created_at),
                    ])->values()->all(),
                ];
            }
        }

        $room = max(0, self::MAX_CIRCLES - count($groups) + 1);
        $chosen = $user ? $user->categories()->orderBy('sort_order')->get() : collect();
        $categories = ($chosen->isNotEmpty() ? $chosen : Category::active()->orderBy('sort_order')->get());

        $recent = Story::published()->with('versions')
            ->whereIn('category_id', $categories->pluck('id'))
            ->where('published_at', '>=', now()->subDays(3))
            ->latest('published_at')
            ->get()
            ->groupBy('category_id');

        foreach ($categories as $category) {
            if ($room <= 0) {
                break;
            }
            $stories = $recent->get($category->id);
            if (! $stories || $stories->isEmpty()) {
                continue;
            }
            $groups[] = [
                'kind' => 'category', 'label' => $category->name(), 'avatar' => $category->imageUrl(), 'badge' => null,
                'slides' => $stories->take(self::CATEGORY_SLIDES)->map($slide)->values()->all(),
            ];
            $room--;
        }

        return array_values(array_filter($groups, fn ($g) => ! empty($g['slides'])));
    }
}
