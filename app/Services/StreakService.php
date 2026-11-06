<?php

namespace App\Services;

use App\Models\QuizAttempt;
use App\Models\ReadingEvent;
use App\Models\Streak;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Daily streaks, measured in IST.
 * - A day counts when the reader reads 3 stories (reading_events) or finishes the daily quiz.
 * - Each reader gets 2 freezes per calendar month; UpdateStreaks (00:05 IST) spends one
 *   automatically for a missed day, or resets the streak when none are left.
 */
class StreakService
{
    public const READS_PER_DAY = 3;
    public const FREEZES_PER_MONTH = 2;

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** Call after a read or a finished quiz. Returns [streak, increasedToday]. */
    public function recordActivity(User $user): array
    {
        $today = self::today();
        $streak = $this->streakFor($user);

        if ($streak->last_day && $streak->last_day->isSameDay($today)) {
            return [$streak, false];
        }
        if (! $this->dayCounts($user, $today)) {
            return [$streak, false];
        }

        $continues = $streak->last_day && $streak->last_day->isSameDay($today->subDay());
        $streak->current = $continues ? $streak->current + 1 : 1;
        $streak->longest = max($streak->longest, $streak->current);
        $streak->last_day = $today->toDateString();
        $streak->save();

        return [$streak, true];
    }

    /** True when the reader read 3 stories or finished the quiz on this IST day. */
    public function dayCounts(User $user, CarbonImmutable $day): bool
    {
        $date = $day->toDateString();

        $reads = ReadingEvent::where('user_id', $user->id)->whereDate('read_on', $date)->count();
        if ($reads >= self::READS_PER_DAY) {
            return true;
        }

        return QuizAttempt::where('user_id', $user->id)
            ->whereNotNull('completed_at')
            ->whereHas('quiz', fn ($q) => $q->whereDate('date', $date))
            ->exists();
    }

    /** Nightly (00:05 IST): cover yesterday with a freeze, or reset. Returns 'kept' | 'frozen' | 'reset'. */
    public function closeDay(Streak $streak): string
    {
        $yesterday = self::today()->subDay();
        $this->resetFreezesIfNewMonth($streak);

        if (! $streak->current || ! $streak->last_day || $streak->last_day->gte($yesterday)) {
            $streak->save();

            return 'kept';
        }

        // last_day is before yesterday → yesterday was missed.
        if ($streak->freezes_left > 0) {
            $streak->freezes_left--;
            $streak->last_day = $yesterday->toDateString(); // the freeze stands in for yesterday
            $streak->save();

            return 'frozen';
        }

        $streak->current = 0;
        $streak->save();

        return 'reset';
    }

    /** The last 7 IST days (oldest first) with whether each counted: for the "This week" card. */
    public function week(User $user): array
    {
        $today = self::today();
        $start = $today->subDays(6);

        $readDays = ReadingEvent::where('user_id', $user->id)
            ->where('read_on', '>=', $start->toDateString())
            ->get(['read_on'])
            ->countBy(fn ($e) => $e->read_on->toDateString());

        $quizDays = QuizAttempt::where('user_id', $user->id)->whereNotNull('completed_at')
            ->with('quiz:id,date')->get()
            ->map(fn ($a) => $a->quiz?->date?->toDateString())->filter()->flip();

        return collect(range(0, 6))->map(function ($i) use ($start, $today, $readDays, $quizDays) {
            $day = $start->addDays($i);
            $date = $day->toDateString();

            return [
                'date' => $date,
                'label' => $day->translatedFormat('D'),
                'done' => ($readDays[$date] ?? 0) >= self::READS_PER_DAY || isset($quizDays[$date]),
                'today' => $day->isSameDay($today),
            ];
        })->all();
    }

    public function streakFor(User $user): Streak
    {
        $streak = Streak::firstOrNew(['user_id' => $user->id], [
            'current' => 0, 'longest' => 0, 'freezes_left' => self::FREEZES_PER_MONTH,
        ]);
        $this->resetFreezesIfNewMonth($streak);

        return $streak;
    }

    private function resetFreezesIfNewMonth(Streak $streak): void
    {
        $month = self::today()->format('Y-m');
        if ($streak->freezes_month !== $month) {
            $streak->freezes_month = $month;
            $streak->freezes_left = self::FREEZES_PER_MONTH;
        }
    }
}
