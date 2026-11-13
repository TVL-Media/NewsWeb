<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Story;
use App\Services\Newsroom\StoryWorkflow;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Approval queue: AI drafts that failed a check (sensitive, low confidence, copy, facts) and
 * stories submitted by writers. AI checks and the source reports sit next to the draft.
 */
class ReviewController extends Controller
{
    use Concerns;

    public function __construct(private StoryWorkflow $wf) {}

    public function index(Request $r)
    {
        $me = auth('admin')->user();
        $tab = $r->query('tab', 'all');
        $base = Story::where('status', 'pending_approval');
        $q = (clone $base)->with(['versions:id,story_id,language,headline', 'category:id,name_en', 'aiCheck', 'author:id,name', 'reviewer:id,name']);
        match ($tab) {
            'ai' => $q->whereNull('author_id'),
            'newsroom' => $q->whereNotNull('author_id'),
            'sensitive' => $q->whereHas('aiCheck', fn ($c) => $c->where('risk_level', 'sensitive')),
            'mine' => $q->where('assigned_reviewer_id', $me->id),
            'unassigned' => $q->whereNull('assigned_reviewer_id'),
            default => null,
        };
        if ($c = $r->query('category')) {
            $q->where('category_id', $c);
        }
        $items = $q->orderByRaw('COALESCE(submitted_at, updated_at)')->paginate($this->perPage($r))->withQueryString();
        $counts = [
            'all' => (clone $base)->count(),
            'ai' => (clone $base)->whereNull('author_id')->count(),
            'newsroom' => (clone $base)->whereNotNull('author_id')->count(),
            'sensitive' => (clone $base)->whereHas('aiCheck', fn ($c) => $c->where('risk_level', 'sensitive'))->count(),
            'unassigned' => (clone $base)->whereNull('assigned_reviewer_id')->count(),
            'mine' => (clone $base)->where('assigned_reviewer_id', $me->id)->count(),
        ];
        $changes = Story::where('status', 'changes_requested')->count();

        return view('admin.review.index', ['items' => $items, 'counts' => $counts, 'tab' => $tab, 'sla' => (int) setting('editorial.review_sla_hours', 2), 'changes' => $changes]);
    }

    public function show(Story $story)
    {
        $story->load(['versions', 'category', 'page', 'aiCheck', 'cluster.items.feedSource', 'approvals.editor', 'author', 'reviewer', 'tags']);
        $reviewers = Admin::where('status', 'active')->get()->filter(fn ($a) => $a->can('review.decide'))->values();

        return view('admin.review.show', [
            's' => $story, 'reviewers' => $reviewers, 'missing' => $this->wf->missing($story),
            'reasons' => (array) setting('editorial.rejection_reasons', []),
        ]);
    }

    public function take(Story $story)
    {
        $story->update(['assigned_reviewer_id' => auth('admin')->id()]);
        Audit::log('story.review_taken', $story);

        return back()->with('ok', 'Assigned to you.');
    }

    public function assign(Request $r, Story $story)
    {
        $data = $r->validate(['reviewer_id' => 'nullable|exists:admins,id']);
        $story->update(['assigned_reviewer_id' => $data['reviewer_id'] ?? null]);
        Audit::log('story.review_assigned', $story, null, ['reviewer_id' => $data['reviewer_id'] ?? null]);
        if ($id = $data['reviewer_id'] ?? null) {
            \App\Support\Notify::admin((int) $id, 'review', 'A story was assigned to you', \Illuminate\Support\Str::limit($story->title(), 120), route('admin.review.show', $story));
        }

        return back()->with('ok', 'Reviewer updated.');
    }

    public function decide(Request $r, Story $story)
    {
        $me = $r->user('admin');
        $data = $r->validate([
            'decision' => 'required|in:approve,approve_schedule,reject,changes',
            'comment' => 'nullable|string|max:2000|required_if:decision,reject,changes',
            'reason' => 'nullable|string|max:80|required_if:decision,reject',
            'scheduled_at' => 'nullable|string|required_if:decision,approve_schedule',
        ], ['comment.required_if' => 'Add a comment so the writer knows what to change.', 'reason.required_if' => 'Choose a rejection reason.']);
        if ($story->status !== 'pending_approval') {
            return back()->with('error', 'This story is no longer waiting for review.');
        }

        try {
            switch ($data['decision']) {
                case 'reject':
                    $this->wf->reject($story, $me, trim($data['reason'].': '.($data['comment'] ?? ''), ': '));
                    $msg = 'Rejected.';
                    break;
                case 'changes':
                    $this->wf->requestChanges($story, $me, $data['comment']);
                    $msg = 'Changes requested. The writer has been told.';
                    break;
                case 'approve_schedule':
                    $this->need('content.schedule');
                    $at = from_local_input($data['scheduled_at']);
                    $this->wf->approve($story, $me, $at);
                    $msg = 'Approved and scheduled for '.fmt_dt($at).'.';
                    break;
                default:
                    $this->wf->approve($story, $me);
                    $msg = 'Approved and published.';
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with('error', 'Not ready to publish yet. Open it in the editor to fix it.');
        }

        $next = Story::where('status', 'pending_approval')->where('id', '!=', $story->id)->orderByRaw('COALESCE(submitted_at, updated_at)')->first();

        return $next ? redirect()->route('admin.review.show', $next)->with('ok', $msg.' Here is the next story.')
            : redirect()->route('admin.review.index')->with('ok', $msg.' The queue is clear.');
    }

    /** Publish your own story without a second editor. Password-confirmed and audit-logged. */
    public function override(Request $r, Story $story)
    {
        $data = $r->validate(['reason' => 'required|string|max:1000']);
        try {
            $this->wf->approve($story, $r->user('admin'), null, override: true);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        Audit::log('story.review_override', $story, null, null, $data['reason']);

        return redirect()->route('admin.review.index')->with('ok', 'Published with override. This is recorded in the audit log.');
    }
}
