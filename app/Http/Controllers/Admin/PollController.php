<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ModerationAction;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Report;
use App\Models\Story;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Website polls: editorial polls (written here) and reader polls (created on the website,
 * waiting for approval). One vote per reader; results on the website appear after voting.
 */
class PollController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $q = Poll::with(['story.versions:id,story_id,language,headline', 'creator:id,name', 'admin:id,name', 'category:id,name_en', 'options']);
        $tab = $r->query('tab', ($r->query('status') ? 'all' : (Poll::where('status', 'pending')->exists() ? 'pending' : 'all')));
        if ($tab === 'pending') {
            $q->where('status', 'pending');
        } elseif ($st = $r->query('status')) {
            $q->where('status', $st);
        }
        if ($src = $r->query('source')) {
            $src === 'readers' ? $q->whereNotNull('created_by') : $q->whereNull('created_by');
        }
        if ($s = $r->query('q')) {
            $q->where('question', 'like', "%{$s}%");
        }
        $polls = $q->latest('id')->paginate($this->perPage($r))->withQueryString();

        // Unusually low participation = voters below a share of the median of live/closed polls.
        $totals = Poll::whereIn('status', ['live', 'closed'])->withSum('options as vote_total', 'votes_count')->get(['id', 'created_at']);
        $median = $totals->pluck('vote_total')->map(fn ($v) => (int) $v)->median() ?: 0;
        $ratio = (float) setting('polls.low_participation_ratio', 0.3);
        $lowIds = $totals->filter(fn ($p) => $median > 0 && (int) $p->vote_total < $median * $ratio && $p->created_at->lt(now()->subHours(6)))->pluck('id');
        $counts = Poll::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $reported = Report::where('target_type', 'poll')->whereIn('status', ['open', 'escalated'])->pluck('target_id')->countBy();

        return view('admin.polls.index', compact('polls', 'lowIds', 'median', 'counts', 'tab', 'reported'));
    }

    public function create(Request $r)
    {
        $poll = new Poll(['status' => 'live', 'story_id' => $r->query('story'), 'ends_at' => now()->addDay()]);

        return $this->form($poll);
    }

    public function edit(Poll $poll)
    {
        return $this->form($poll->load('options'));
    }

    private function form(Poll $poll)
    {
        return view('admin.polls.form', [
            'poll' => $poll,
            'categories' => Category::active()->orderBy('sort_order')->get(['id', 'name_en']),
            'stories' => Story::published()->with('versions:id,story_id,language,headline')->latest('published_at')->limit(80)->get()->mapWithKeys(fn ($s) => [$s->id => $s->title()]),
            'locked' => $poll->exists && $poll->votes()->exists(),
        ]);
    }

    private function validated(Request $r, ?Poll $poll): array
    {
        $locked = $poll && $poll->votes()->exists();
        $rules = [
            'story_id' => 'nullable|integer|exists:stories,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'ends_at' => 'nullable|string',
        ];
        if (! $locked) {
            $rules += ['question' => 'required|string|min:10|max:150', 'options' => 'required|array|min:2|max:4', 'options.*' => 'required|string|max:60'];
        }
        $data = $r->validate($rules, ['options.min' => 'Add at least 2 options.', 'options.max' => 'The website shows up to 4 options.']);
        $data['ends_at'] = from_local_input($data['ends_at'] ?? null);
        if ($data['ends_at'] && $data['ends_at']->isPast()) {
            back()->withInput()->withErrors(['ends_at' => 'The end time must be in the future.'])->throwResponse();
        }

        return $data;
    }

    public function store(Request $r)
    {
        $data = $this->validated($r, null);
        $poll = DB::transaction(function () use ($data, $r) {
            $poll = Poll::create([
                'question' => $data['question'], 'story_id' => $data['story_id'] ?? null, 'category_id' => $data['category_id'] ?? null,
                'ends_at' => $data['ends_at'], 'status' => $r->input('go') === 'draft' ? 'pending' : 'live', 'admin_id' => auth('admin')->id(),
            ]);
            foreach (array_values($data['options']) as $i => $label) {
                $poll->options()->create(['label' => $label, 'sort_order' => $i]);
            }

            return $poll;
        });
        Audit::log('poll.created', $poll, null, ['status' => $poll->status]);
        PageCache::flush();

        return redirect()->route('admin.polls.show', $poll)->with('ok', $poll->status === 'live' ? 'Poll is live on the website.' : 'Poll saved. Approve it to put it live.');
    }

    public function update(Request $r, Poll $poll)
    {
        $data = $this->validated($r, $poll);
        $before = $poll->only('question', 'story_id', 'category_id', 'ends_at');
        DB::transaction(function () use ($poll, $data) {
            $poll->update(collect($data)->only('question', 'story_id', 'category_id', 'ends_at')->all());
            if (isset($data['options'])) {
                $poll->options()->delete();
                foreach (array_values($data['options']) as $i => $label) {
                    $poll->options()->create(['label' => $label, 'sort_order' => $i]);
                }
            }
        });
        Audit::log('poll.updated', $poll, $before, $poll->only(array_keys($before)));
        PageCache::flush();

        return redirect()->route('admin.polls.show', $poll)->with('ok', 'Poll saved.');
    }

    public function show(Poll $poll)
    {
        $poll->load(['options', 'story.versions:id,story_id,language,headline', 'creator', 'admin', 'category']);
        $total = $poll->totalVotes();
        $series = PollVote::where('poll_id', $poll->id)->get(['created_at'])
            ->groupBy(fn ($v) => $v->created_at->copy()->setTimezone(ptz())->format($poll->created_at->gt(now()->subDays(2)) ? 'Y-m-d H:00' : 'Y-m-d'))
            ->map->count()->sortKeys();
        $reports = Report::where('target_type', 'poll')->where('target_id', $poll->id)->whereIn('status', ['open', 'escalated'])->count();

        return view('admin.polls.show', ['poll' => $poll, 'results' => $this->results($poll), 'total' => $total, 'series' => $series, 'reports' => $reports]);
    }

    /** Percentages that always add up to 100 (largest remainder). */
    private function results(Poll $poll): array
    {
        $total = max(1, $poll->totalVotes());
        $rows = $poll->options->map(fn ($o) => ['option' => $o, 'votes' => (int) $o->votes_count, 'exact' => $o->votes_count * 100 / $total])->values()->all();
        $floor = array_map(fn ($r) => (int) floor($r['exact']), $rows);
        $left = $poll->totalVotes() ? 100 - array_sum($floor) : 0;
        $order = collect($rows)->keys()->sortByDesc(fn ($k) => $rows[$k]['exact'] - $floor[$k])->values();
        foreach ($order->take($left) as $k) {
            $floor[$k]++;
        }
        foreach ($rows as $k => &$row) {
            $row['percent'] = $floor[$k];
        }

        return $rows;
    }

    /** Approve a reader poll: it keeps the run length the reader chose, counted from now. */
    public function approve(Poll $poll)
    {
        abort_unless($poll->status === 'pending', 422);
        $days = max(1, (int) round($poll->created_at->diffInHours($poll->ends_at ?? $poll->created_at->copy()->addDay()) / 24));
        $poll->update(['status' => 'live', 'ends_at' => $poll->isEditorial() && $poll->ends_at?->isFuture() ? $poll->ends_at : now()->addDays($days)]);
        Audit::log('poll.approved', $poll, ['status' => 'pending'], ['status' => 'live']);
        PageCache::flush();

        return back()->with('ok', 'Poll approved. It is live until '.fmt_dt($poll->ends_at).'.');
    }

    public function reject(Request $r, Poll $poll)
    {
        $reason = $r->input('reason');
        $from = $poll->status;
        $poll->update(['status' => 'rejected', 'rejection_reason' => $reason]);
        Report::where('target_type', 'poll')->where('target_id', $poll->id)->whereIn('status', ['open', 'escalated'])->update(['status' => 'actioned', 'handled_by' => auth('admin')->id(), 'handled_at' => now()]);
        ModerationAction::create(['target_type' => 'poll', 'target_id' => $poll->id, 'action' => 'reject', 'note' => $reason, 'admin_id' => auth('admin')->id()]);
        Audit::log('poll.rejected', $poll, ['status' => $from], ['status' => 'rejected'], $reason);
        PageCache::flush();

        return back()->with('ok', 'Poll rejected'.($from === 'live' ? ' and taken off the website.' : '.'));
    }

    public function close(Request $r, Poll $poll)
    {
        abort_unless($poll->status === 'live', 422);
        $poll->update(['status' => 'closed', 'ends_at' => now()]);
        Audit::log('poll.closed', $poll, ['status' => 'live'], ['status' => 'closed'], $r->input('reason'));
        PageCache::flush();

        return back()->with('ok', 'Poll closed. Results stay visible.');
    }

    public function duplicate(Poll $poll)
    {
        $copy = DB::transaction(function () use ($poll) {
            $copy = Poll::create(['question' => $poll->question, 'story_id' => $poll->story_id, 'category_id' => $poll->category_id, 'status' => 'pending', 'admin_id' => auth('admin')->id(), 'ends_at' => now()->addDay()]);
            foreach ($poll->options as $o) {
                $copy->options()->create(['label' => $o->label, 'sort_order' => $o->sort_order]);
            }

            return $copy;
        });
        Audit::log('poll.duplicated', $copy, null, ['from' => $poll->id]);

        return redirect()->route('admin.polls.edit', $copy)->with('ok', 'Copy created with no votes. Edit it, then approve it.');
    }

    public function destroy(Poll $poll)
    {
        abort_if($poll->votes()->exists(), 422, 'Readers already voted in this poll. Close or reject it instead.');
        $poll->delete();
        Audit::log('poll.deleted', $poll);

        return redirect()->route('admin.polls.index')->with('ok', 'Poll deleted.');
    }

    public function export(Poll $poll)
    {
        $poll->load('options');
        $rows = $this->results($poll);

        return response()->streamDownload(function () use ($poll, $rows) {
            $fh = fopen('php://output', 'w');
            fputcsv($fh, ['Poll', $poll->question]);
            fputcsv($fh, ['Status', $poll->status, 'Ends', $poll->ends_at?->toIso8601String()]);
            fputcsv($fh, ['Option', 'Votes', 'Percent']);
            foreach ($rows as $r) {
                fputcsv($fh, [$r['option']->label, $r['votes'], $r['percent']]);
            }
            fclose($fh);
        }, 'poll-'.$poll->id.'.csv', ['Content-Type' => 'text/csv']);
    }
}
