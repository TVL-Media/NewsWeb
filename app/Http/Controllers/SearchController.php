<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Page;
use App\Models\Story;
use App\Models\StoryVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** /search?q=…&type=all|stories|pages&category=slug */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $type = in_array($request->query('type'), ['stories', 'pages'], true) ? $request->query('type') : 'all';
        $category = $request->filled('category') ? Category::where('slug', $request->query('category'))->first() : null;

        $stories = collect();
        $pages = collect();

        if (mb_strlen($q) >= 2) {
            if ($type !== 'pages') {
                $stories = $this->stories($q, $category);
            }
            if ($type !== 'stories') {
                $pages = Page::query()
                    ->withCount('followers')
                    ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                        ->orWhere('name_hi', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%"))
                    ->when($category, fn ($w) => $w->where('category_id', $category->id))
                    ->orderByDesc('followers_count')
                    ->limit(10)
                    ->get();
            }
        }

        return view('search', [
            'q' => $q,
            'type' => $type,
            'category' => $category,
            'categories' => Category::active()->orderBy('sort_order')->get(),
            'stories' => $stories,
            'pages' => $pages,
        ]);
    }

    /** MySQL full-text on story_versions(headline, card_text), with a LIKE fallback (short words, SQLite in tests). */
    private function stories(string $q, ?Category $category): Collection
    {
        $ids = collect();

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $ids = StoryVersion::query()
                ->whereFullText(['headline', 'card_text'], $q)
                ->limit(60)
                ->pluck('story_id');
        }

        if ($ids->isEmpty()) {
            $ids = StoryVersion::query()
                ->where(fn ($w) => $w->where('headline', 'like', "%{$q}%")->orWhere('card_text', 'like', "%{$q}%"))
                ->limit(60)
                ->pluck('story_id');
        }

        if ($ids->isEmpty()) {
            return collect();
        }

        $order = $ids->unique()->values()->flip(); // keep relevance order

        return Story::published()
            ->with(['versions', 'category'])
            ->whereIn('id', $ids->unique())
            ->when($category, fn ($w) => $w->where('category_id', $category->id))
            ->get()
            ->sortBy(fn ($s) => $order[$s->id] ?? PHP_INT_MAX)
            ->take(30)
            ->values();
    }
}
