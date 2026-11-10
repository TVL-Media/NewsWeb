<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Streak;
use App\Services\Analytics\AnalyticsService;
use App\Services\StreakService;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Streak rules used by the website (reads per day, freezes per month) and streak stats. */
class StreakController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        [$from, $to, $label, $preset] = $this->range($r, '30d');
        $an = AnalyticsService::range($from, $to);
        $yesterday = StreakService::today()->subDay()->toDateString();
        $leaders = Streak::with('user:id,name')->orderByDesc('longest')->limit(10)->get();
        $activeNow = Streak::with('user:id,name')->where('current', '>', 0)->where('last_day', '>=', $yesterday)->orderByDesc('current')->limit(10)->get();

        return view('admin.streaks.index', [
            'summary' => $an->streakSummary(), 'series' => $an->streakDaysSeries(), 'dist' => $an->streakDistribution(),
            'leaders' => $leaders, 'activeNow' => $activeNow, 'from' => $from, 'to' => $to, 'label' => $label, 'preset' => $preset,
            'canManage' => auth('admin')->user()->can('streaks.manage'),
            'history' => \App\Models\AuditLog::with('admin:id,name')->where('action', 'streak.rules_changed')->latest('created_at')->limit(10)->get(),
        ]);
    }

    public function save(Request $r)
    {
        $data = $r->validate(['reads_per_day' => 'required|integer|min:1|max:20', 'freezes_per_month' => 'required|integer|min:0|max:10', 'change_note' => 'nullable|string|max:250']);
        $before = ['reads_per_day' => StreakService::readsPerDay(), 'freezes_per_month' => StreakService::freezesPerMonth()];
        Settings::set('streaks.reads_per_day', (int) $data['reads_per_day'], auth('admin')->id());
        Settings::set('streaks.freezes_per_month', (int) $data['freezes_per_month'], auth('admin')->id());
        Audit::log('streak.rules_changed', null, $before, ['reads_per_day' => (int) $data['reads_per_day'], 'freezes_per_month' => (int) $data['freezes_per_month']], $data['change_note'] ?? null, 'Streak rules');

        return back()->with('ok', 'Streak rules saved. They apply from today; past days are not recounted.');
    }
}
