<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Briefing;
use App\Models\FeedSource;
use App\Models\Poll;
use App\Models\Quiz;
use App\Models\Report;
use App\Models\Story;
use App\Models\TranslationJob;
use App\Services\Analytics\AnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $this->need('dashboard.view');
        [$from, $to, $label, $preset] = $this->range($r);
        $key = 'dash:v2:'.md5($from.$to);

        // Plain arrays only: Laravel 13's cache won't unserialize objects.
        $data = Cache::remember($key, 300, function () use ($from, $to) {
            $an = AnalyticsService::range($from, $to);
            $pv = $an->previous();
            $d = ['computed_at' => now()->toIso8601String()];
            $d['published_total'] = Story::published()->count();
            $d['published_period'] = $an->publishedCount();
            $d['published_auto'] = $an->publishedCount('auto');
            $d['published_change'] = pct_change($d['published_period'], $pv->publishedCount());
            $d['reads'] = $an->reads();
            $d['reads_change'] = pct_change($d['reads'], $pv->reads());
            $d['active_users'] = $an->activeUsers();
            $d['active_change'] = pct_change($d['active_users'], $pv->activeUsers());
            $d['new_users'] = $an->newUsers();
            $d['total_users'] = $an->totalUsers();
            $d['likes'] = $an->likes();
            $d['comments'] = $an->comments();
            $d['shares'] = $an->shares();
            $d['polls'] = $an->pollSummary();
            $d['streaks'] = $an->streakSummary();
            $d['quiz'] = $an->quizSummary();
            $d['newsroom'] = $an->newsroom();
            $d['series'] = $an->readsSeries();
            $d['top_read'] = $an->topStories('reads', 6);
            $d['top_engaged'] = $an->topStories('engagement', 6);
            $d['categories'] = array_slice($an->byCategory(true), 0, 6);

            return $d;
        });

        // Live counts (not cached).
        $live = [
            'pending_review' => Story::where('status', 'pending_approval')->count(),
            'review_overdue' => Story::where('status', 'pending_approval')->whereRaw('COALESCE(submitted_at, updated_at) < ?', [now()->subHours((int) setting('editorial.review_sla_hours', 2))])->count(),
            'scheduled' => Story::where('status', 'scheduled')->count(),
            'next_scheduled' => Story::where('status', 'scheduled')->orderBy('scheduled_at')->value('scheduled_at'),
            'failed_publish' => Story::where('status', 'scheduled')->where('publish_attempts', '>', 0)->count(),
            'reports_open' => Report::whereIn('status', ['open', 'escalated'])->count(),
            'reports_escalated' => Report::where('status', 'escalated')->count(),
            'polls_pending' => Poll::where('status', 'pending')->count(),
            'translations_review' => TranslationJob::where('status', 'ai_completed')->count(),
            'no_hindi' => Story::published()->where('published_at', '>=', now()->subDay())->whereDoesntHave('versions', fn ($v) => $v->where('language', 'hi'))->count(),
            'briefing_today' => Briefing::whereDate('date', now(config('app.timezone'))->toDateString())->exists(),
            'quiz_tomorrow' => Quiz::whereDate('date', now(config('app.timezone'))->addDay()->toDateString())->value('status'),
            'feeds_stale' => FeedSource::where('active', true)->where(fn ($q) => $q->whereNull('last_fetched_at')->orWhere('last_fetched_at', '<', now()->subMinutes(30)))->count(),
        ];
        $upcoming = Story::with(['versions:id,story_id,language,headline', 'category:id,name_en'])->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now()->subDay(), now()->addDays(7)])->orderBy('scheduled_at')->limit(8)->get();

        return view('admin.dashboard', compact('data', 'live', 'upcoming', 'from', 'to', 'label', 'preset'));
    }
}
