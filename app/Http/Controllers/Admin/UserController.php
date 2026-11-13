<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\QuizAttempt;
use App\Models\ReadingEvent;
use App\Models\Report;
use App\Models\Save;
use App\Models\Share;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\StreakService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Website readers (phone + OTP accounts). */
class UserController extends Controller
{
    use Concerns;

    public const REASONS = ['spam' => 'Spam or fake account', 'abuse' => 'Harassment or abuse', 'misinformation' => 'Spreading misinformation',
        'impersonation' => 'Impersonation', 'repeat_violations' => 'Repeated violations', 'user_request' => 'Reader’s request', 'appeal_upheld' => 'Appeal upheld', 'other' => 'Other'];

    public const STATUSES = ['active' => ['Active', 'green'], 'restricted' => ['Restricted', 'amber'], 'suspended' => ['Suspended', 'red']];

    public function index(Request $r)
    {
        $q = User::query()->with(['streak', 'city:id,name_en']);
        if ($s = trim((string) $r->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('id', (int) ltrim($s, '#')));
        }
        if ($st = $r->query('status')) {
            $q->where('status', $st);
        }
        if ($l = $r->query('language')) {
            $q->where('language', $l);
        }
        if ($c = $r->query('city')) {
            $q->where('city_id', $c);
        }
        match ($r->query('activity')) {
            '7d' => $q->where('last_active_at', '>=', now()->subDays(7)),
            '30d' => $q->where('last_active_at', '>=', now()->subDays(30)),
            'dormant' => $q->where(fn ($w) => $w->whereNull('last_active_at')->orWhere('last_active_at', '<', now()->subDays(30))),
            default => null,
        };
        // Engagement from reads in the last 7 days: high ≥ 10, medium 3–9, low < 3.
        if ($e = $r->query('engagement')) {
            $reads = ReadingEvent::select('user_id', DB::raw('count(*) as n'))->where('read_at', '>=', now()->subDays(7))->groupBy('user_id');
            $q->leftJoinSub($reads, 'rd', 'rd.user_id', '=', 'users.id')->select('users.*');
            match ($e) {
                'high' => $q->where('rd.n', '>=', 10), 'medium' => $q->whereBetween('rd.n', [3, 9]), default => $q->where(fn ($w) => $w->whereNull('rd.n')->orWhere('rd.n', '<', 3)),
            };
        }
        $users = $q->orderByDesc('users.id')->paginate($this->perPage($r))->withQueryString();
        $ids = $users->pluck('id');
        $reads = ReadingEvent::whereIn('user_id', $ids)->select('user_id', DB::raw('count(*) as n'))->groupBy('user_id')->pluck('n', 'user_id');
        $comments = Comment::whereIn('user_id', $ids)->select('user_id', DB::raw('count(*) as n'))->groupBy('user_id')->pluck('n', 'user_id');
        $counts = User::select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return view('admin.users.index', ['users' => $users, 'reads' => $reads, 'comments' => $comments, 'counts' => $counts,
            'cities' => \App\Models\City::orderBy('name_en')->get(['id', 'name_en']), 'pii' => auth('admin')->user()->can('users.view.pii')]);
    }

    public function show(User $user)
    {
        $user->load(['streak', 'city', 'categories:id,name_en', 'followedPages:id,name', 'statusLogs.admin:id,name']);
        $summary = [
            'reads' => ReadingEvent::where('user_id', $user->id)->count(),
            'likes' => Like::where('user_id', $user->id)->count(),
            'comments' => Comment::where('user_id', $user->id)->count(),
            'hidden' => Comment::where('user_id', $user->id)->where('status', 'hidden')->count(),
            'saves' => Save::where('user_id', $user->id)->count(),
            'shares' => Share::where('user_id', $user->id)->count(),
            'votes' => PollVote::where('user_id', $user->id)->count(),
            'quizzes' => QuizAttempt::where('user_id', $user->id)->whereNotNull('completed_at')->count(),
            'polls' => Poll::where('created_by', $user->id)->count(),
            'reports_made' => Report::where('reporter_id', $user->id)->count(),
        ];
        $recentReads = ReadingEvent::with('story.versions:id,story_id,language,headline')->where('user_id', $user->id)->latest('read_at')->limit(15)->get();
        $recentComments = Comment::with('story.versions:id,story_id,language,headline')->where('user_id', $user->id)->latest('id')->limit(10)->get();
        $reportsAbout = Report::where(fn ($q) => $q->where('target_type', 'comment')->whereIn('target_id', Comment::where('user_id', $user->id)->select('id')))
            ->orWhere(fn ($q) => $q->where('target_type', 'poll')->whereIn('target_id', Poll::where('created_by', $user->id)->select('id')))->latest()->limit(10)->get();

        return view('admin.users.show', ['u' => $user, 'summary' => $summary, 'recentReads' => $recentReads, 'recentComments' => $recentComments,
            'reports' => $reportsAbout, 'week' => app(StreakService::class)->week($user), 'pii' => auth('admin')->user()->can('users.view.pii')]);
    }

    /** Active / restricted / suspended, with a recorded reason. The website applies it on the reader's next request. */
    public function status(Request $r, User $user)
    {
        $data = $r->validate(['to' => 'required|in:active,restricted,suspended', 'reason' => 'required|in:'.implode(',', array_keys(self::REASONS)), 'note' => 'nullable|string|max:1000', 'days' => 'nullable|integer|min:1|max:365']);
        $me = auth('admin')->user();
        abort_if($data['to'] === 'suspended' && ! $me->can('users.suspend'), 403);
        abort_if($data['to'] === 'restricted' && ! $me->can('users.restrict'), 403);
        abort_if($data['to'] === 'active' && ! ($me->can('users.restrict') || $me->can('users.suspend')), 403);
        $from = $user->status;
        $until = $data['to'] === 'restricted' && ! empty($data['days']) ? now()->addDays((int) $data['days']) : null;
        $user->update(['status' => $data['to'], 'restricted_until' => $until]);
        if ($data['to'] === 'suspended') {
            DB::table('sessions')->where('user_id', $user->id)->delete(); // signs them out everywhere
        }
        UserStatusLog::create(['user_id' => $user->id, 'from_status' => $from, 'to_status' => $data['to'], 'reason' => $data['reason'], 'note' => $data['note'] ?? null, 'until' => $until, 'admin_id' => $me->id, 'created_at' => now()]);
        Audit::log('user.'.$data['to'], $user, ['status' => $from], ['status' => $data['to'], 'until' => $until?->toIso8601String()], self::REASONS[$data['reason']].(! empty($data['note']) ? ' — '.$data['note'] : ''), 'Reader #'.$user->id);

        return back()->with('ok', ($user->name ?: 'Reader #'.$user->id).' is now '.strtolower(self::STATUSES[$data['to']][0]).($until ? ' until '.fmt_dt($until) : '').'.');
    }

    public function recalcStreak(User $user, StreakService $s)
    {
        $st = $s->recalculate($user);
        Audit::log('user.streak_recalculated', $user, null, ['current' => $st->current, 'longest' => $st->longest], 'Support recalculation', 'Reader #'.$user->id);

        return back()->with('ok', "Streak recalculated: current {$st->current}, longest {$st->longest}.");
    }

    public function grantFreeze(User $user, StreakService $s)
    {
        $st = $s->streakFor($user);
        $st->freezes_left = min(10, $st->freezes_left + 1);
        $st->save();
        Audit::log('user.freeze_granted', $user, null, ['freezes_left' => $st->freezes_left], 'Support', 'Reader #'.$user->id);

        return back()->with('ok', "Freeze added. {$st->freezes_left} left this month.");
    }

    /** Data access request: everything we hold about the reader, as JSON. */
    public function export(User $user)
    {
        Audit::log('user.data_exported', $user, null, null, 'Data access request', 'Reader #'.$user->id);
        $data = [
            'profile' => $user->only('id', 'name', 'phone', 'language', 'text_size', 'theme', 'notifications_per_day', 'status', 'created_at', 'last_active_at'),
            'city' => $user->city?->name_en,
            'topics' => $user->categories()->pluck('name_en'),
            'following' => $user->followedPages()->pluck('name'),
            'streak' => $user->streak?->only('current', 'longest', 'last_day'),
            'reads' => ReadingEvent::where('user_id', $user->id)->latest('read_at')->limit(5000)->get(['story_id', 'read_at']),
            'likes' => Like::where('user_id', $user->id)->get(['likeable_type', 'likeable_id', 'created_at']),
            'comments' => Comment::where('user_id', $user->id)->get(['story_id', 'body', 'status', 'created_at']),
            'saves' => Save::where('user_id', $user->id)->get(['story_id', 'created_at']),
            'votes' => PollVote::where('user_id', $user->id)->get(['poll_id', 'poll_option_id', 'created_at']),
            'quiz_attempts' => QuizAttempt::where('user_id', $user->id)->get(['quiz_id', 'score', 'completed_at']),
        ];

        return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), "reader-{$user->id}-data.json", ['Content-Type' => 'application/json']);
    }

    /** Right to erasure: remove the phone number and name, sign out, delete the account. */
    public function anonymise(User $user)
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        DB::table('otp_requests')->where('phone', $user->phone)->delete();
        $user->forceFill(['name' => 'Deleted reader #'.$user->id, 'phone' => 'deleted-'.$user->id, 'status' => 'suspended', 'remember_token' => null])->save();
        $user->delete();
        Audit::log('user.anonymised', $user, null, null, 'Erasure request', 'Reader #'.$user->id);

        return redirect()->route('admin.users.index')->with('ok', 'Reader anonymised. Their phone number is removed; counts stay without personal data.');
    }
}
