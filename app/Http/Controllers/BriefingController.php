<?php

namespace App\Http\Controllers;

use App\Models\Briefing;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Quiz;
use App\Services\StreakService;
use App\Support\Rails;
use Illuminate\Http\Request;

/** /briefing: Morning 5 player, daily quiz card, "This week" streak card and polls. */
class BriefingController extends Controller
{
    public function __invoke(Request $request, StreakService $streaks)
    {
        $user = $request->user();
        $briefing = Briefing::today() ?? Briefing::latest('date')->first();
        $stories = $briefing ? $briefing->stories() : collect();

        $polls = Poll::with('options')
            ->where(fn ($q) => $q->where('status', 'live')->orWhere(fn ($c) => $c->where('status', 'closed')->where('ends_at', '>=', now()->subDays(3))))
            ->orderByRaw("CASE WHEN status = 'live' THEN 0 ELSE 1 END")
            ->latest()
            ->limit(9)
            ->get();

        $quiz = Quiz::today();
        $attempt = $user && $quiz ? $user->quizAttempts()->where('quiz_id', $quiz->id)->whereNotNull('completed_at')->first() : null;

        return view('briefing', [
            'briefing' => $briefing,
            'stories' => $stories,
            'trending' => Rails::trending()->loadMissing('category'),
            'polls' => $polls,
            'votes' => $user ? PollVote::where('user_id', $user->id)->whereIn('poll_id', $polls->pluck('id'))->pluck('poll_option_id', 'poll_id')->all() : [],
            'streak' => Rails::streakDays($user),
            'week' => $user ? $streaks->week($user) : null,
            'quizDone' => $attempt ? $attempt->score.'/'.$quiz->questions()->count() : null,
            'hasQuiz' => $quiz && in_array($quiz->status, ['approved', 'published'], true),
        ]);
    }
}
