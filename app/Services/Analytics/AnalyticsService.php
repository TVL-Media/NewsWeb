<?php

namespace App\Services\Analytics;

use App\Models\AiCheck;
use App\Models\Approval;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\QuizAttempt;
use App\Models\ReadingEvent;
use App\Models\Report;
use App\Models\Save;
use App\Models\Share;
use App\Models\Story;
use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin analytics computed straight from the website's own tables (reading_events, likes,
 * comments, shares, saves, poll_votes, quiz_attempts, reports, ai_checks). Everything returns
 * plain arrays so results can be cached (Laravel 13 won't unserialize objects from the cache).
 *
 * "Read" = a reading_events row: the website records it after 3 s on a card or 8 s on the story page.
 */
class AnalyticsService
{
    public Carbon $from;

    public Carbon $to;

    /** @param string $fromLocal Y-m-d in the panel timezone */
    public function __construct(public string $fromLocal, public string $toLocal, public array $filters = [])
    {
        $this->from = Carbon::parse($fromLocal, ptz())->startOfDay()->utc();
        $this->to = Carbon::parse($toLocal, ptz())->endOfDay()->utc();
    }

    public static function range(string $from, string $to, array $filters = []): self
    {
        return new self($from, $to, $filters);
    }

    public function previous(): self
    {
        $days = Carbon::parse($this->fromLocal)->diffInDays(Carbon::parse($this->toLocal)) + 1;

        return new self(Carbon::parse($this->fromLocal)->subDays($days)->toDateString(), Carbon::parse($this->fromLocal)->subDay()->toDateString(), $this->filters);
    }

    public function sameLastYear(): self
    {
        return new self(Carbon::parse($this->fromLocal)->subYear()->toDateString(), Carbon::parse($this->toLocal)->subYear()->toDateString(), $this->filters);
    }

    /** Local calendar days in the range. */
    public function days(): array
    {
        $out = [];
        for ($d = Carbon::parse($this->fromLocal); $d->lte(Carbon::parse($this->toLocal)); $d->addDay()) {
            $out[] = $d->toDateString();
        }

        return $out;
    }

    private function within($q, string $col = 'created_at')
    {
        return $q->whereBetween($col, [$this->from, $this->to]);
    }

    /** Restrict a query on a story-linked table by the category filter. */
    private function storyFilter($q, string $storyCol = 'story_id')
    {
        if ($c = $this->filters['category_id'] ?? null) {
            $q->whereIn($storyCol, Story::where('category_id', $c)->select('id'));
        }

        return $q;
    }

    /** Group timestamps by local day in PHP (portable across MySQL and SQLite). */
    private function perDay(Collection $timestamps): array
    {
        $counts = $timestamps->countBy(fn ($t) => Carbon::parse($t)->setTimezone(ptz())->toDateString());

        return array_map(fn ($d) => (int) ($counts[$d] ?? 0), $this->days());
    }

    // ── Content ─────────────────────────────────────────────────────────────

    public function publishedCount(?string $mode = null): int
    {
        $q = Story::whereIn('status', ['published', 'corrected'])->whereBetween('published_at', [$this->from, $this->to]);
        if ($mode) {
            $q->where('publish_mode', $mode);
        }
        if ($c = $this->filters['category_id'] ?? null) {
            $q->where('category_id', $c);
        }

        return $q->count();
    }

    public function reads(): int
    {
        return $this->storyFilter($this->within(ReadingEvent::query(), 'read_at'))->count();
    }

    public function likes(): int
    {
        $q = $this->within(Like::where('likeable_type', Story::class));
        if ($c = $this->filters['category_id'] ?? null) {
            $q->whereIn('likeable_id', Story::where('category_id', $c)->select('id'));
        }

        return $q->count();
    }

    public function comments(): int
    {
        return $this->storyFilter($this->within(Comment::query()))->count();
    }

    public function shares(): int
    {
        return $this->storyFilter($this->within(Share::query()))->count();
    }

    public function saves(): int
    {
        return $this->storyFilter($this->within(Save::query()))->count();
    }

    public function readsSeries(): array
    {
        $engage = collect()
            ->merge($this->storyFilter($this->within(Like::where('likeable_type', Story::class)))->pluck('created_at'))
            ->merge($this->storyFilter($this->within(Comment::query()))->pluck('created_at'))
            ->merge($this->storyFilter($this->within(Share::query()))->pluck('created_at'));

        return [
            'labels' => $this->days(),
            'reads' => $this->perDay($this->storyFilter($this->within(ReadingEvent::query(), 'read_at'))->pluck('read_at')),
            'engagement' => $this->perDay($engage),
        ];
    }

    public function publishingSeries(): array
    {
        $q = Story::whereIn('status', ['published', 'corrected'])->whereBetween('published_at', [$this->from, $this->to]);
        if ($c = $this->filters['category_id'] ?? null) {
            $q->where('category_id', $c);
        }
        $rows = $q->get(['published_at', 'publish_mode']);

        return [
            'labels' => $this->days(),
            'auto' => $this->perDay($rows->where('publish_mode', 'auto')->pluck('published_at')),
            'manual' => $this->perDay($rows->where('publish_mode', 'manual')->pluck('published_at')),
        ];
    }

    /** Top stories by reads, or by engagement = reads + 2×likes + 3×comments + 3×shares (in the period). */
    public function topStories(string $by = 'reads', int $limit = 10): array
    {
        $reads = $this->storyFilter($this->within(ReadingEvent::query(), 'read_at'))->select('story_id', DB::raw('count(*) as n'))->groupBy('story_id')->pluck('n', 'story_id');
        $scores = $reads->map(fn ($n) => (int) $n);
        if ($by === 'engagement') {
            $add = function ($rows, int $w) use (&$scores) {
                foreach ($rows as $id => $n) {
                    $scores[$id] = ($scores[$id] ?? 0) + $w * (int) $n;
                }
            };
            $add($this->within(Like::where('likeable_type', Story::class))->select('likeable_id', DB::raw('count(*) as n'))->groupBy('likeable_id')->pluck('n', 'likeable_id'), 2);
            $add($this->within(Comment::query())->select('story_id', DB::raw('count(*) as n'))->groupBy('story_id')->pluck('n', 'story_id'), 3);
            $add($this->within(Share::query())->select('story_id', DB::raw('count(*) as n'))->groupBy('story_id')->pluck('n', 'story_id'), 3);
        }
        $top = $scores->sortDesc()->take($limit);
        $stories = Story::with(['versions:id,story_id,language,headline', 'category:id,name_en'])->whereIn('id', $top->keys())->get()->keyBy('id');
        if ($c = $this->filters['category_id'] ?? null) {
            $stories = $stories->where('category_id', (int) $c);
        }

        return $top->filter(fn ($n, $id) => isset($stories[$id]))->map(fn ($n, $id) => [
            'id' => $id, 'title' => $stories[$id]->title(), 'category' => $stories[$id]->category?->name_en,
            'mode' => $stories[$id]->publish_mode, 'value' => $n, 'reads' => (int) ($reads[$id] ?? 0),
        ])->values()->all();
    }

    /** Reads, likes, comments and shares per category, with the change in reads vs the previous period. */
    public function byCategory(bool $withChange = false): array
    {
        $sum = fn ($q, $col = 'story_id') => $q->join('stories', 'stories.id', '=', $col)->select('stories.category_id', DB::raw('count(*) as n'))->groupBy('stories.category_id')->pluck('n', 'category_id');
        $reads = $sum($this->within(ReadingEvent::query(), 'reading_events.read_at'), 'reading_events.story_id');
        $likes = $sum($this->within(Like::where('likeable_type', Story::class), 'likes.created_at'), 'likes.likeable_id');
        $comments = $sum($this->within(Comment::query(), 'comments.created_at'), 'comments.story_id');
        $shares = $sum($this->within(Share::query(), 'shares.created_at'), 'shares.story_id');
        $published = Story::whereIn('status', ['published', 'corrected'])->whereBetween('published_at', [$this->from, $this->to])
            ->select('category_id', DB::raw('count(*) as n'))->groupBy('category_id')->pluck('n', 'category_id');
        $prev = $withChange ? $this->previous()->byCategory(false) : [];
        $prevReads = collect($prev)->pluck('reads', 'id');

        return \App\Models\Category::orderBy('sort_order')->get(['id', 'name_en', 'color_hex'])->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name_en, 'color' => $c->color_hex ?: '#D7141E',
            'published' => (int) ($published[$c->id] ?? 0), 'reads' => (int) ($reads[$c->id] ?? 0), 'likes' => (int) ($likes[$c->id] ?? 0),
            'comments' => (int) ($comments[$c->id] ?? 0), 'shares' => (int) ($shares[$c->id] ?? 0),
            'change' => $withChange ? pct_change((int) ($reads[$c->id] ?? 0), (int) ($prevReads[$c->id] ?? 0)) : null,
        ])->sortByDesc('reads')->values()->all();
    }

    public function shareChannels(): array
    {
        return $this->storyFilter($this->within(Share::query()))->select('channel', DB::raw('count(*) as n'))->groupBy('channel')->orderByDesc('n')->pluck('n', 'channel')->map(fn ($n) => (int) $n)->all();
    }

    public function reactions(): array
    {
        return $this->storyFilter($this->within(DB::table('reactions')))->select('type', DB::raw('count(*) as n'))->groupBy('type')->orderByDesc('n')->pluck('n', 'type')->map(fn ($n) => (int) $n)->all();
    }

    // ── Newsroom ────────────────────────────────────────────────────────────

    public function newsroom(): array
    {
        $checks = $this->within(AiCheck::query())->get(['decision', 'risk_level', 'created_at']);
        $approvals = $this->within(Approval::query(), 'acted_at')->with('story:id,created_at')->get(['story_id', 'action', 'acted_at']);
        $waits = $approvals->filter(fn ($a) => $a->story)->map(fn ($a) => $a->story->created_at->diffInMinutes($a->acted_at))->filter(fn ($m) => $m >= 0);

        return [
            'drafts' => $checks->count(),
            'auto' => $checks->where('decision', 'auto')->count(),
            'to_editor' => $checks->where('decision', 'approval')->count(),
            'sensitive' => $checks->where('risk_level', 'sensitive')->count(),
            'approved' => $approvals->whereIn('action', ['approved', 'edited'])->count(),
            'rejected' => $approvals->where('action', 'rejected')->count(),
            'median_minutes' => $waits->isEmpty() ? null : (int) $waits->median(),
            'series' => ['labels' => $this->days(), 'auto' => $this->perDay($checks->where('decision', 'auto')->pluck('created_at')), 'to_editor' => $this->perDay($checks->where('decision', 'approval')->pluck('created_at'))],
        ];
    }

    // ── Readers ─────────────────────────────────────────────────────────────

    /** Readers who read, liked, commented, voted or played the quiz in the period. */
    public function activeUsers(): int
    {
        return $this->activeUserIds()->count();
    }

    private function activeUserIds(): Collection
    {
        return collect()
            ->merge($this->within(ReadingEvent::query(), 'read_at')->distinct()->pluck('user_id'))
            ->merge($this->within(Like::query())->distinct()->pluck('user_id'))
            ->merge($this->within(Comment::query())->distinct()->pluck('user_id'))
            ->merge($this->within(PollVote::query())->distinct()->pluck('user_id'))
            ->merge($this->within(QuizAttempt::query())->distinct()->pluck('user_id'))
            ->unique();
    }

    public function newUsers(): int
    {
        return $this->within(User::query())->count();
    }

    public function totalUsers(): int
    {
        return User::count();
    }

    /** Active readers who joined before the period. */
    public function returningUsers(): int
    {
        $ids = $this->activeUserIds();

        return $ids->isEmpty() ? 0 : User::whereIn('id', $ids)->where('created_at', '<', $this->from)->count();
    }

    public function activeSeries(): array
    {
        $rows = collect()
            ->merge($this->within(ReadingEvent::query(), 'read_at')->get(['user_id', 'read_at as at']))
            ->merge($this->within(Like::query())->get(['user_id', 'created_at as at']))
            ->merge($this->within(Comment::query())->get(['user_id', 'created_at as at']))
            ->merge($this->within(PollVote::query())->get(['user_id', 'created_at as at']));
        $byDay = $rows->groupBy(fn ($r) => Carbon::parse($r->at)->setTimezone(ptz())->toDateString())->map(fn ($g) => $g->pluck('user_id')->unique()->count());

        return ['labels' => $this->days(), 'active' => array_map(fn ($d) => (int) ($byDay[$d] ?? 0), $this->days()),
            'new' => $this->perDay($this->within(User::query())->pluck('created_at'))];
    }

    public function languageSplit(): array
    {
        return User::select('language', DB::raw('count(*) as n'))->groupBy('language')->pluck('n', 'language')->map(fn ($n) => (int) $n)->all();
    }

    public function topCities(int $limit = 8): array
    {
        return User::join('cities', 'cities.id', '=', 'users.city_id')->select('cities.name_en', DB::raw('count(*) as n'))
            ->groupBy('cities.name_en')->orderByDesc('n')->limit($limit)->pluck('n', 'name_en')->map(fn ($n) => (int) $n)->all();
    }

    // ── Habits: streaks, quiz, Morning 5 ────────────────────────────────────

    public function streakSummary(): array
    {
        $yesterday = StreakService::today()->subDay()->toDateString();
        $active = Streak::where('current', '>', 0)->where('last_day', '>=', $yesterday);

        return [
            'active' => (clone $active)->count(),
            'avg' => round((float) (clone $active)->avg('current'), 1),
            'longest' => (int) Streak::max('longest'),
            'freezes_used' => (int) Streak::where('freezes_month', now(config('app.timezone'))->format('Y-m'))->get()->sum(fn ($s) => max(0, StreakService::freezesPerMonth() - $s->freezes_left)),
        ];
    }

    /** Readers whose day counted (N reads or a finished quiz), per day. */
    public function streakDaysSeries(): array
    {
        $n = StreakService::readsPerDay();
        $reads = ReadingEvent::whereBetween('read_on', [$this->fromLocal, $this->toLocal])
            ->select('user_id', 'read_on', DB::raw('count(*) as c'))->groupBy('user_id', 'read_on')->get()
            ->filter(fn ($r) => $r->c >= $n)->map(fn ($r) => [Carbon::parse($r->read_on)->toDateString(), $r->user_id]);
        $quiz = QuizAttempt::whereNotNull('completed_at')->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->whereBetween('quizzes.date', [$this->fromLocal, $this->toLocal])->get(['quiz_attempts.user_id', 'quizzes.date'])
            ->map(fn ($r) => [Carbon::parse($r->date)->toDateString(), $r->user_id]);
        $byDay = $reads->merge($quiz)->unique(fn ($p) => $p[0].'-'.$p[1])->countBy(fn ($p) => $p[0]);

        return ['labels' => $this->days(), 'values' => array_map(fn ($d) => (int) ($byDay[$d] ?? 0), $this->days())];
    }

    public function streakDistribution(): array
    {
        $yesterday = StreakService::today()->subDay()->toDateString();
        $vals = Streak::where('current', '>', 0)->where('last_day', '>=', $yesterday)->pluck('current');
        $buckets = ['1–2' => [1, 2], '3–6' => [3, 6], '7–13' => [7, 13], '14–29' => [14, 29], '30+' => [30, PHP_INT_MAX]];

        return collect($buckets)->map(fn ($r) => $vals->filter(fn ($v) => $v >= $r[0] && $v <= $r[1])->count())->all();
    }

    public function quizSummary(): array
    {
        $attempts = QuizAttempt::whereNotNull('completed_at')->whereBetween('completed_at', [$this->from, $this->to])->get(['score', 'completed_at']);

        return [
            'players' => $attempts->count(),
            'avg_score' => $attempts->isEmpty() ? null : round($attempts->avg('score'), 1),
            'series' => ['labels' => $this->days(), 'values' => $this->perDay($attempts->pluck('completed_at'))],
        ];
    }

    // ── Polls ───────────────────────────────────────────────────────────────

    public function pollSummary(): array
    {
        $votes = $this->within(PollVote::query());

        return [
            'votes' => (clone $votes)->count(),
            'voters' => (clone $votes)->distinct()->count('user_id'),
            'live' => Poll::live()->count(),
            'reader_polls' => $this->within(Poll::whereNotNull('created_by'))->count(),
            'reader_pending' => Poll::where('status', 'pending')->count(),
            'series' => ['labels' => $this->days(), 'values' => $this->perDay((clone $votes)->pluck('created_at'))],
        ];
    }

    public function topPolls(int $limit = 8): array
    {
        $counts = $this->within(PollVote::query())->select('poll_id', DB::raw('count(*) as n'))->groupBy('poll_id')->orderByDesc('n')->limit($limit)->pluck('n', 'poll_id');
        $polls = Poll::whereIn('id', $counts->keys())->get(['id', 'question', 'status'])->keyBy('id');

        return $counts->filter(fn ($n, $id) => isset($polls[$id]))->map(fn ($n, $id) => ['id' => $id, 'question' => $polls[$id]->question, 'status' => $polls[$id]->status, 'votes' => (int) $n])->values()->all();
    }

    // ── Moderation ──────────────────────────────────────────────────────────

    public function moderationSummary(): array
    {
        $reports = $this->within(Report::query())->get(['status', 'reason', 'target_type', 'created_at', 'handled_at']);
        $handled = $reports->filter(fn ($r) => $r->handled_at);
        $hours = $handled->map(fn ($r) => Carbon::parse($r->created_at)->diffInMinutes(Carbon::parse($r->handled_at)) / 60);

        return [
            'reports' => $reports->count(),
            'open' => Report::whereIn('status', ['open', 'escalated'])->count(),
            'actioned' => $reports->where('status', 'actioned')->count(),
            'dismissed' => $reports->where('status', 'dismissed')->count(),
            'median_hours' => $hours->isEmpty() ? null : round($hours->median(), 1),
            'by_reason' => $reports->countBy('reason')->all(),
            'by_type' => $reports->countBy('target_type')->all(),
            'comments_hidden' => $this->within(DB::table('moderation_actions'))->whereIn('action', ['hide', 'take_down', 'restrict_user', 'suspend_user'])->where('target_type', 'comment')->count(),
            'series' => ['labels' => $this->days(), 'values' => $this->perDay($reports->pluck('created_at'))],
        ];
    }
}
