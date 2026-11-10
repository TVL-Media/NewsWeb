<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** M7 Scheduling: calendar + list (PRD §5.5). */
class ScheduleController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Story::with(['versions:id,story_id,language,headline', 'category:id,name_en', 'author:id,name'])->where('status', 'scheduled');
        if ($r->boolean('failed')) {
            $q->where('publish_attempts', '>', 0);
        }
        $list = $q->orderBy('scheduled_at')->paginate(50)->withQueryString();
        $recent = Story::with('versions:id,story_id,language,headline')->whereIn('status', ['published', 'corrected'])->where('published_at', '>=', now()->subDays(2))->orderByDesc('published_at')->limit(8)->get();

        return view('admin.schedule.index', ['list' => $list, 'recent' => $recent, 'view' => $r->query('view', 'calendar')]);
    }

    /** FullCalendar feed: scheduled (blue), failed (red), published (green). */
    public function events(Request $r)
    {
        $from = Carbon::parse($r->query('start', now()->startOfMonth()))->utc();
        $to = Carbon::parse($r->query('end', now()->endOfMonth()))->utc();
        $items = Story::with('versions:id,story_id,language,headline')->where(function ($w) use ($from, $to) {
            $w->where(fn ($s) => $s->where('status', 'scheduled')->whereBetween('scheduled_at', [$from, $to]))
                ->orWhere(fn ($p) => $p->whereIn('status', ['published', 'corrected'])->whereBetween('published_at', [$from, $to]));
        })->limit(800)->get();
        $canMove = auth('admin')->user()->can('content.schedule');

        return response()->json($items->map(function (Story $s) use ($canMove) {
            $scheduled = $s->status === 'scheduled';
            $failed = $scheduled && $s->publish_attempts > 0;
            $at = $scheduled ? $s->scheduled_at : $s->published_at;

            return [
                'id' => $s->id, 'title' => $s->title(), 'start' => $at->copy()->setTimezone(ptz())->toIso8601String(),
                'url' => route('admin.stories.edit', $s), 'editable' => $scheduled && $canMove,
                'backgroundColor' => $failed ? '#C8102E' : ($scheduled ? '#245BA8' : ($s->publish_mode === 'auto' ? '#6B7280' : '#1E7B4E')),
                'extendedProps' => ['status' => $failed ? 'Failed' : ($scheduled ? 'Scheduled' : ($s->publish_mode === 'auto' ? 'Auto-published' : 'Published'))],
            ];
        }));
    }

    /** Drag on the calendar to reschedule (confirmed in the UI). */
    public function move(Request $r, Story $story)
    {
        $data = $r->validate(['start' => 'required|date']);
        abort_unless($story->status === 'scheduled', 422, 'Only scheduled stories can be moved.');
        $at = Carbon::parse($data['start'])->utc();
        if ($at->isPast()) {
            return response()->json(['message' => 'Pick a time in the future.'], 422);
        }
        $before = $story->scheduled_at;
        $story->update(['scheduled_at' => $at, 'publish_attempts' => 0, 'last_publish_error' => null]);
        Audit::log('story.rescheduled', $story, ['scheduled_at' => $before?->toIso8601String()], ['scheduled_at' => $at->toIso8601String()]);

        return response()->json(['ok' => true, 'label' => fmt_dt($at)]);
    }
}
