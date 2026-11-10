<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Analytics\AnalyticsService;
use App\Support\Audit;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use Concerns;

    /** Tab => [label, permission that unlocks it besides analytics.all]. */
    public const TABS = [
        'content' => ['Stories', 'content.view'],
        'newsroom' => ['Newsroom & AI', 'review.view'],
        'readers' => ['Readers', 'users.view'],
        'habits' => ['Polls, quiz & streaks', 'polls.view|quiz.manage'],
        'moderation' => ['Moderation', 'reports.view'],
    ];

    public static function visibleTabs(): array
    {
        $a = auth('admin')->user();

        return collect(self::TABS)->filter(fn ($t) => $a->can('analytics.all') || collect(explode('|', $t[1]))->contains(fn ($p) => $a->can($p)))->all();
    }

    public function index(Request $r, ?string $tab = null)
    {
        $tabs = self::visibleTabs();
        abort_if(empty($tabs), 403, 'Your role has no analytics areas.');
        $tab ??= array_key_first($tabs);
        abort_unless(isset($tabs[$tab]), 403);
        [$from, $to, $label, $preset] = $this->range($r, '30d');
        $an = AnalyticsService::range($from, $to, ['category_id' => $r->query('category')]);
        $cmp = match ($r->query('compare')) { 'previous' => $an->previous(), 'last_year' => $an->sameLastYear(), default => null };

        return view('admin.analytics.index', compact('tab', 'tabs', 'from', 'to', 'label', 'preset') + [
            'd' => $this->data($tab, $an, $cmp), 'cmp' => $cmp, 'categories' => Category::orderBy('sort_order')->get(['id', 'name_en']),
        ]);
    }

    private function data(string $tab, AnalyticsService $an, ?AnalyticsService $cmp): array
    {
        $c = fn ($fn) => $cmp ? $fn($cmp) : null;

        return match ($tab) {
            'content' => [
                'published' => $an->publishedCount(), 'published_cmp' => $c(fn ($x) => $x->publishedCount()),
                'reads' => $an->reads(), 'reads_cmp' => $c(fn ($x) => $x->reads()),
                'likes' => $an->likes(), 'likes_cmp' => $c(fn ($x) => $x->likes()),
                'shares' => $an->shares(), 'shares_cmp' => $c(fn ($x) => $x->shares()),
                'series' => $an->readsSeries(), 'series_cmp' => $c(fn ($x) => $x->readsSeries()),
                'publishing' => $an->publishingSeries(), 'by_category' => $an->byCategory(true),
                'top' => $an->topStories('reads', 15), 'engaged' => $an->topStories('engagement', 10),
                'channels' => $an->shareChannels(), 'reactions' => $an->reactions(),
            ],
            'newsroom' => ['n' => $an->newsroom(), 'n_cmp' => $c(fn ($x) => $x->newsroom())],
            'readers' => [
                'active' => $an->activeUsers(), 'active_cmp' => $c(fn ($x) => $x->activeUsers()),
                'new' => $an->newUsers(), 'new_cmp' => $c(fn ($x) => $x->newUsers()),
                'returning' => $an->returningUsers(), 'total' => $an->totalUsers(),
                'series' => $an->activeSeries(), 'languages' => $an->languageSplit(), 'cities' => $an->topCities(),
            ],
            'habits' => [
                'polls' => $an->pollSummary(), 'top_polls' => $an->topPolls(), 'quiz' => $an->quizSummary(),
                'streaks' => $an->streakSummary(), 'streak_days' => $an->streakDaysSeries(), 'dist' => $an->streakDistribution(),
            ],
            'moderation' => ['m' => $an->moderationSummary(), 'm_cmp' => $c(fn ($x) => $x->moderationSummary())],
        };
    }

    /** CSV of the current tab, downloaded straight away. */
    public function export(Request $r, string $tab)
    {
        abort_unless(isset(self::visibleTabs()[$tab]), 403);
        [$from, $to] = $this->range($r, '30d');
        $an = AnalyticsService::range($from, $to, ['category_id' => $r->input('category')]);
        $rows = $this->rows($tab, $an);
        Audit::log('analytics.exported', null, null, ['report' => $tab, 'rows' => count($rows), 'from' => $from, 'to' => $to], null, self::TABS[$tab][0].' report');

        return response()->streamDownload(function () use ($tab, $from, $to, $rows) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Report', self::TABS[$tab][0], 'From', $from, 'To', $to, 'Timezone', ptz()]);
            foreach ($rows as $row) {
                fputcsv($fh, $row);
            }
            fclose($fh);
        }, "{$tab}-{$from}-to-{$to}.csv", ['Content-Type' => 'text/csv']);
    }

    private function rows(string $tab, AnalyticsService $an): array
    {
        $daily = function (array $series, array $cols) {
            $out = [array_merge(['Date'], array_values($cols))];
            foreach ($series['labels'] as $i => $d) {
                $out[] = array_merge([$d], array_map(fn ($k) => $series[$k][$i] ?? 0, array_keys($cols)));
            }

            return $out;
        };

        return match ($tab) {
            'content' => array_merge(
                $daily($an->readsSeries(), ['reads' => 'Reads', 'engagement' => 'Likes+comments+shares']), [[]],
                [['Story ID', 'Headline', 'Category', 'Reads']], array_map(fn ($s) => [$s['id'], $s['title'], $s['category'], $s['value']], $an->topStories('reads', 100)), [[]],
                [['Category', 'Published', 'Reads', 'Likes', 'Comments', 'Shares']], array_map(fn ($c) => [$c['name'], $c['published'], $c['reads'], $c['likes'], $c['comments'], $c['shares']], $an->byCategory()),
            ),
            'newsroom' => $daily($an->newsroom()['series'], ['auto' => 'Auto-published', 'to_editor' => 'Sent to an editor']),
            'readers' => $daily($an->activeSeries(), ['active' => 'Active readers', 'new' => 'New sign-ups']),
            'habits' => array_merge($daily($an->pollSummary()['series'], ['values' => 'Poll votes']), [[]], $daily($an->streakDaysSeries(), ['values' => 'Readers whose day counted'])),
            'moderation' => $daily($an->moderationSummary()['series'], ['values' => 'Reports']),
        };
    }
}
