<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\ModerationAction;
use App\Models\Story;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Reader comments: reported first; hiding keeps the story's comment count right. */
class CommentController extends Controller
{
    use Concerns;

    public function index(Request $r)
    {
        $reported = fn ($q) => $q->whereExists(fn ($e) => $e->from('reports')->whereColumn('reports.target_id', 'comments.id')
            ->where('reports.target_type', 'comment')->whereIn('reports.status', ['open', 'escalated']));
        $q = Comment::with(['user:id,name,phone,status', 'story.versions:id,story_id,language,headline', 'reel:id,title,slug,status'])
            ->select('comments.*')
            ->selectSub(fn ($s) => $s->from('reports')->selectRaw('count(*)')->whereColumn('reports.target_id', 'comments.id')->where('reports.target_type', 'comment')->whereIn('reports.status', ['open', 'escalated']), 'open_reports');
        $tab = $r->query('tab', 'reported');
        match ($tab) {
            'reported' => $reported($q),
            'hidden' => $q->where('status', 'hidden'),
            default => null,
        };
        if ($s = $r->query('q')) {
            $q->where('body', 'like', "%{$s}%");
        }
        if ($u = $r->query('user')) {
            $q->where('user_id', $u);
        }

        return view('admin.comments.index', [
            'comments' => $q->latest('id')->paginate($this->perPage($r))->withQueryString(),
            'tab' => $tab,
            'counts' => ['reported' => $reported(Comment::query())->count(), 'hidden' => Comment::where('status', 'hidden')->count(), 'all' => Comment::count()],
        ]);
    }

    public function hide(Request $r, Comment $comment)
    {
        self::setStatus($comment, 'hidden', $r->input('reason'));

        return back()->with('ok', 'Comment hidden.');
    }

    public function restore(Comment $comment)
    {
        self::setStatus($comment, 'visible', null);

        return back()->with('ok', 'Comment restored.');
    }

    /** Shared with the reports screen. Closes open reports on the comment. */
    public static function setStatus(Comment $comment, string $status, ?string $reason): int
    {
        if ($comment->status === $status) {
            return 0;
        }
        $comment->update(['status' => $status]);
        // A comment belongs to a story or to a reel; keep the right count in step.
        $owner = $comment->reel_id ? \App\Models\Reel::whereKey($comment->reel_id) : Story::whereKey($comment->story_id);
        $status === 'hidden'
            ? $owner->where('comments_count', '>', 0)->decrement('comments_count')
            : $owner->increment('comments_count');
        $closed = DB::table('reports')->where('target_type', 'comment')->where('target_id', $comment->id)->whereIn('status', ['open', 'escalated'])
            ->update(['status' => $status === 'hidden' ? 'actioned' : 'dismissed', 'handled_by' => auth('admin')->id(), 'handled_at' => now(), 'updated_at' => now()]);
        ModerationAction::create(['target_type' => 'comment', 'target_id' => $comment->id, 'action' => $status === 'hidden' ? 'hide' : 'restore', 'note' => $reason, 'reports_closed' => $closed, 'admin_id' => auth('admin')->id()]);
        Audit::log($status === 'hidden' ? 'comment.hidden' : 'comment.restored', $comment, null, null, $reason, 'Comment #'.$comment->id);

        return $closed;
    }
}
