<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FetchFeeds;
use App\Models\AiCheck;
use App\Models\AutoPublishSetting;
use App\Models\Category;
use App\Models\FeedItem;
use App\Models\FeedSource;
use App\Models\Source;
use App\Models\Story;
use App\Services\Newsroom\StoryWorkflow;
use App\Support\Audit;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Newsroom automation: feeds, auto-publish rules and the auto-publish log. */
class NewsroomController extends Controller
{
    use Concerns;

    // ── Feeds ───────────────────────────────────────────────────────────────

    public function feeds()
    {
        $feeds = FeedSource::withCount(['items', 'items as items_today' => fn ($q) => $q->where('created_at', '>=', now()->subDay())])->orderBy('name')->get();

        return view('admin.newsroom.feeds', [
            'feeds' => $feeds,
            'sources' => Source::withCount('stories')->orderBy('name')->get(),
            'pending' => FeedItem::where('processed', false)->count(),
        ]);
    }

    private function feedRules(): array
    {
        return ['name' => 'required|string|max:120', 'url' => 'required|url|max:255', 'type' => 'required|in:agency,rss', 'trusted' => 'nullable|boolean', 'active' => 'nullable|boolean'];
    }

    public function feedStore(Request $r)
    {
        $data = $r->validate($this->feedRules());
        $f = FeedSource::create($data + ['trusted' => $r->boolean('trusted'), 'active' => $r->boolean('active', true)]);
        Audit::log('feed.created', $f, null, $f->only('name', 'url', 'trusted', 'active'));

        return back()->with('ok', "Feed “{$f->name}” added. It is fetched every 5 minutes.");
    }

    public function feedUpdate(Request $r, FeedSource $feed)
    {
        $data = $r->validate($this->feedRules());
        $before = $feed->only('name', 'url', 'type', 'trusted', 'active');
        $feed->update($data + ['trusted' => $r->boolean('trusted'), 'active' => $r->boolean('active')]);
        Audit::log('feed.updated', $feed, $before, $feed->only(array_keys($before)));

        return back()->with('ok', 'Feed saved.');
    }

    public function feedToggle(Request $r, FeedSource $feed)
    {
        $field = $r->validate(['field' => 'required|in:active,trusted'])['field'];
        $feed->update([$field => ! $feed->{$field}]);
        Audit::log('feed.'.$field.'_toggled', $feed, [$field => ! $feed->{$field}], [$field => $feed->{$field}]);

        return back()->with('ok', ucfirst($field).' '.($feed->{$field} ? 'on' : 'off').' for “'.$feed->name.'”.');
    }

    public function feedFetch(FeedSource $feed)
    {
        FetchFeeds::dispatch($feed->id);
        Audit::log('feed.fetch_requested', $feed);

        return back()->with('ok', 'Fetching “'.$feed->name.'” now. New stories appear in the approval queue or go live within a few minutes.');
    }

    public function feedDestroy(FeedSource $feed)
    {
        $feed->delete();
        Audit::log('feed.deleted', $feed);

        return back()->with('ok', 'Feed removed. Stories already written from it stay.');
    }

    public function sourceStore(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:120', 'url' => 'nullable|url|max:255']);
        Source::create($data);

        return back()->with('ok', 'Source added.');
    }

    // ── Auto-publish rules ───────────────────────────────────────────────────

    public function rules()
    {
        $s = AutoPublishSetting::current();
        $since = now()->subDays(7);
        $stats = AiCheck::where('created_at', '>=', $since)->select('decision', DB::raw('count(*) as n'))->groupBy('decision')->pluck('n', 'decision');

        return view('admin.newsroom.rules', [
            's' => $s, 'categories' => Category::orderBy('sort_order')->get(['id', 'slug', 'name_en']),
            'stats' => $stats, 'aiReady' => app(\App\Services\AiWriter::class)->enabled(),
        ]);
    }

    public function rulesSave(Request $r)
    {
        $data = $r->validate([
            'sensitive_categories' => 'nullable|array', 'sensitive_categories.*' => 'string|exists:categories,slug',
            'sensitive_keywords' => 'nullable|string|max:5000',
            'confidence_threshold' => 'required|numeric|min:0|max:1',
            'copy_threshold' => 'required|numeric|min:0|max:1',
        ]);
        $s = AutoPublishSetting::current();
        $before = $s->only('sensitive_categories', 'sensitive_keywords', 'confidence_threshold', 'copy_threshold');
        $cats = array_values($data['sensitive_categories'] ?? []);
        $s->update([
            'sensitive_categories' => $cats,
            'sensitive_keywords' => collect(preg_split('/[\r\n,]+/', (string) ($data['sensitive_keywords'] ?? '')))->map(fn ($k) => trim($k))->filter()->unique()->values()->all(),
            'confidence_threshold' => (float) $data['confidence_threshold'],
            'copy_threshold' => (float) $data['copy_threshold'],
        ]);
        // Keep the category "sensitive" flag in step with this list.
        Category::query()->update(['is_sensitive' => false]);
        Category::whereIn('slug', $cats)->update(['is_sensitive' => true]);
        Cache::forget('nav.categories');
        Audit::log('autopublish.settings_updated', null, $before, $s->only(array_keys($before)), null, 'Auto-publish rules');

        return back()->with('ok', 'Auto-publish rules saved. They apply to the next drafts.');
    }

    // ── Auto-publish log ─────────────────────────────────────────────────────

    public function log(Request $r)
    {
        $q = AiCheck::with(['story.versions:id,story_id,language,headline', 'story.category:id,name_en'])->latest();
        if ($d = $r->query('decision')) {
            $q->where('decision', $d);
        }
        if ($r->query('risk') === 'sensitive') {
            $q->where('risk_level', 'sensitive');
        }

        return view('admin.newsroom.log', ['checks' => $q->paginate($this->perPage($r))->withQueryString()]);
    }

    public function logUnpublish(Request $r, AiCheck $check, StoryWorkflow $wf)
    {
        $this->need('content.publish');
        $wf->unpublish($check->story, $r->user('admin'), 'Unpublished from the auto-publish log');
        PageCache::flush();

        return back()->with('ok', 'Unpublished.');
    }
}
