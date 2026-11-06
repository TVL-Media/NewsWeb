<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Poll;
use App\Models\Story;
use App\Support\Rails;
use Illuminate\Http\Request;

/** Topic / city / show page, e.g. /page/cricket. Tabs: latest | explainers | polls. */
class PageController extends Controller
{
    public const TABS = ['latest', 'explainers', 'polls'];

    public function show(Request $request, Page $page)
    {
        $page->loadCount('followers')->load('pinnedStory');
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'latest';
        $user = $request->user();

        $stories = Story::published()
            ->with(['versions', 'category', 'source'])
            ->where('page_id', $page->id)
            ->when($tab === 'explainers', fn ($q) => $q->where('read_minutes', '>=', 4)->orderByDesc('read_minutes'))
            ->latest('published_at')
            ->limit(25)
            ->get();

        $polls = $tab === 'polls'
            ? Poll::with('options')->whereIn('status', ['live', 'closed'])
                ->whereHas('story', fn ($q) => $q->where('page_id', $page->id))
                ->latest()->get()
            : collect();

        $followed = Rails::followedPageIds($user);

        $related = Page::query()
            ->whereKeyNot($page->id)
            ->where(fn ($q) => $q->where('type', $page->type)->orWhere('category_id', $page->category_id))
            ->withCount('followers')
            ->orderByDesc('followers_count')
            ->limit(4)
            ->get();

        return view('pages.show', [
            'page' => $page,
            'tab' => $tab,
            'top' => $tab === 'polls' ? null : $stories->first(),
            'more' => $tab === 'polls' ? collect() : $stories->slice(1)->values(),
            'polls' => $polls,
            'updatedAt' => Story::published()->where('page_id', $page->id)->max('published_at'),
            'following' => in_array($page->id, $followed, true),
            'followed' => $followed,
            'related' => $related,
            'upcoming' => collect($page->upcoming ?? [])->filter(fn ($e) => $e['date'] >= now(config('app.timezone'))->toDateString())->values(),
        ]);
    }
}
