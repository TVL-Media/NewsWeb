<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Models\Poll;
use App\Models\Reel;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\Newsroom\StoryWorkflow;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Reader reports on stories, comments and polls. Reports about the same item are grouped. */
class ReportController extends Controller
{
    use Concerns;

    public const ACTIONS = [
        'take_down' => 'Take it down (hide comment / unpublish story / reject poll)',
        'dismiss' => 'Dismiss (no violation)',
        'restrict_user' => 'Take it down and restrict the author for 7 days',
        'suspend_user' => 'Take it down and suspend the author',
        'escalate' => 'Escalate to a Super Admin',
    ];

    public const REASONS = ['spam' => 'Spam', 'abusive' => 'Abusive', 'misinformation' => 'Misinformation', 'other' => 'Other'];

    public function index(Request $r)
    {
        $status = $r->query('status', 'open');
        $q = Report::query()->select('target_type', 'target_id', DB::raw('MAX(id) as id'), DB::raw('count(*) as dupes'), DB::raw('MAX(created_at) as last_at'))
            ->groupBy('target_type', 'target_id');
        if ($status === 'open') {
            $q->whereIn('status', ['open', 'escalated']);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($t = $r->query('type')) {
            $q->where('target_type', $t);
        }
        if ($rs = $r->query('reason')) {
            $q->where('reason', $rs);
        }
        $groups = $q->orderByDesc('last_at')->paginate($this->perPage($r))->withQueryString();
        $reports = Report::with('reporter:id,name')->whereIn('id', $groups->pluck('id'))->get()->keyBy('id');
        $counts = Report::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('admin.reports.index', compact('groups', 'reports', 'counts', 'status'));
    }

    public function show(Report $report)
    {
        $report->load(['reporter', 'handler']);
        $target = $report->target();
        $siblings = Report::with('reporter:id,name')->where('target_type', $report->target_type)->where('target_id', $report->target_id)->where('id', '!=', $report->id)->latest()->get();
        $history = ModerationAction::with('admin:id,name')->where('target_type', $report->target_type)->where('target_id', $report->target_id)->latest('created_at')->get();
        $author = ($id = $report->targetAuthorId()) ? User::withTrashed()->find($id) : null;
        $offences = $author ? ModerationAction::whereIn('action', ['take_down', 'restrict_user', 'suspend_user', 'hide'])
            ->where(fn ($w) => $w->where(fn ($c) => $c->where('target_type', 'comment')->whereIn('target_id', Comment::where('user_id', $author->id)->select('id')))
                ->orWhere(fn ($p) => $p->where('target_type', 'poll')->whereIn('target_id', Poll::where('created_by', $author->id)->select('id'))))->count() : 0;

        return view('admin.reports.show', ['r' => $report, 'target' => $target, 'siblings' => $siblings, 'history' => $history, 'author' => $author, 'offences' => $offences]);
    }

    public function act(Request $r, Report $report, StoryWorkflow $wf)
    {
        $data = $r->validate(['action' => 'required|in:'.implode(',', array_keys(self::ACTIONS)), 'note' => 'nullable|string|max:2000|required_unless:action,dismiss']);
        $me = $r->user('admin');
        $action = $data['action'];
        if ($report->status === 'escalated' && ! $me->isSuperAdmin()) {
            abort(403, 'Escalated reports are decided by a Super Admin.');
        }
        abort_if($action === 'suspend_user' && ! $me->can('users.suspend'), 403);
        abort_if($action === 'restrict_user' && ! $me->can('users.restrict'), 403);
        abort_if($action === 'escalate' && ! $me->can('reports.escalate'), 403);

        $target = $report->target();
        $msg = 'Done.';
        DB::transaction(function () use ($action, $report, $target, $data, $me, $wf, &$msg) {
            if (in_array($action, ['take_down', 'restrict_user', 'suspend_user'], true)) {
                match (true) {
                    $target instanceof Comment => CommentController::setStatus($target, 'hidden', 'Report: '.$data['note']),
                    $target instanceof Story => $target->isLive() ? $wf->unpublish($target, $me, 'Report: '.$data['note']) : null,
                    $target instanceof Poll => $target->update(['status' => 'rejected', 'rejection_reason' => 'Report: '.$data['note']]),
                    $target instanceof Reel => (new ReelController)->takeDown($target, 'reel.taken_down', 'Report: '.$data['note']),
                    default => null,
                };
                PageCache::flush();
                $msg = 'Taken down.';
            }
            if (in_array($action, ['restrict_user', 'suspend_user'], true) && ($uid = $report->targetAuthorId()) && ($u = User::find($uid))) {
                $to = $action === 'suspend_user' ? 'suspended' : 'restricted';
                $until = $to === 'restricted' ? now()->addDays(7) : null;
                $from = $u->status;
                $u->update(['status' => $to, 'restricted_until' => $until]);
                UserStatusLog::create(['user_id' => $u->id, 'from_status' => $from, 'to_status' => $to, 'reason' => 'moderation', 'note' => $data['note'], 'until' => $until, 'admin_id' => $me->id, 'report_id' => $report->id, 'created_at' => now()]);
                Audit::log('user.'.$to, $u, ['status' => $from], ['status' => $to], 'Report #'.$report->id.': '.$data['note'], 'Reader #'.$u->id);
                $msg = 'Taken down and the author '.($to === 'suspended' ? 'suspended.' : 'restricted for 7 days.');
            }
            if ($action === 'escalate') {
                Notify::superAdmins('moderation', 'Report escalated', ucfirst($report->target_type).' #'.$report->target_id.': '.$data['note'], route('admin.reports.show', $report));
                $msg = 'Escalated to a Super Admin.';
            }
            if ($action === 'dismiss') {
                $msg = 'Report dismissed.';
            }

            $new = match ($action) { 'dismiss' => 'dismissed', 'escalate' => 'escalated', default => 'actioned' };
            $closed = Report::where('target_type', $report->target_type)->where('target_id', $report->target_id)->whereIn('status', ['open', 'escalated'])
                ->update(['status' => $new, 'handled_by' => $me->id, 'handled_at' => now()]);
            ModerationAction::create(['report_id' => $report->id, 'target_type' => $report->target_type, 'target_id' => $report->target_id, 'action' => $action, 'reason' => $report->reason, 'note' => $data['note'] ?? null, 'reports_closed' => $new === 'escalated' ? 0 : $closed, 'admin_id' => $me->id, 'created_at' => now()]);
            Audit::log('moderation.'.$action, $report, null, ['target' => $report->target_type.'#'.$report->target_id], $data['note'] ?? null, ucfirst($report->target_type).' #'.$report->target_id);
        });

        $next = Report::whereIn('status', ['open'])->orderBy('created_at')->first();

        return $next ? redirect()->route('admin.reports.show', $next)->with('ok', $msg.' Here is the next report.') : redirect()->route('admin.reports.index')->with('ok', $msg);
    }

    /** Record of moderation decisions, with CSV. */
    public function log(Request $r)
    {
        $q = ModerationAction::with('admin:id,name')->latest('created_at');
        if ($a = $r->query('action')) {
            $q->where('action', $a);
        }
        if ($r->query('export')) {
            Audit::log('moderation.log_exported');

            return response()->streamDownload(function () use ($q) {
                $o = fopen('php://output', 'w');
                fputcsv($o, ['When', 'Admin', 'Action', 'Target', 'Reason', 'Note']);
                $q->chunk(500, function ($rows) use ($o) {
                    foreach ($rows as $m) {
                        fputcsv($o, [$m->created_at->setTimezone(ptz())->toDateTimeString(), $m->admin?->name, $m->action, $m->target_type.'#'.$m->target_id, $m->reason, $m->note]);
                    }
                });
                fclose($o);
            }, 'moderation-log.csv');
        }

        return view('admin.reports.log', ['items' => $q->paginate(50)->withQueryString()]);
    }
}
