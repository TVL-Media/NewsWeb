<?php

namespace App\Jobs;

use App\Models\Admin;
use App\Models\Quiz;
use App\Models\Story;
use App\Services\AiWriter;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * 21:00 IST: draft tomorrow's quiz from today's top 5 stories. It is approved automatically when
 * every source story is low-risk; otherwise it waits as a draft for an editor (Daily › Quizzes).
 */
class DraftQuiz implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public function __construct(public ?string $date = null) {}

    public function handle(AiWriter $ai): void
    {
        $date = $this->date ?? now(config('app.timezone'))->addDay()->toDateString();
        if (Quiz::whereDate('date', $date)->exists()) {
            return;
        }

        $stories = Story::published()
            ->with(['versions', 'page', 'aiCheck'])
            ->where('published_at', '>=', now()->subDay())
            ->orderByDesc('importance')->orderByDesc('likes_count')
            ->limit(5)->get();

        if ($stories->count() < 3) {
            return;
        }

        $questions = $ai->quiz($stories);
        if (count($questions) < 3) {
            return;
        }

        $lowRisk = $stories->whereIn('id', array_column($questions, 'story_id'))
            ->every(fn ($s) => ($s->aiCheck?->risk_level ?? 'low') === 'low');

        $quiz = DB::transaction(function () use ($date, $questions, $lowRisk) {
            $quiz = Quiz::create(['date' => $date, 'status' => $lowRisk ? 'approved' : 'draft']);
            foreach (array_slice($questions, 0, 5) as $i => $q) {
                $quiz->questions()->create([
                    'story_id' => $q['story_id'],
                    'sort_order' => $i + 1,
                    'question' => $q['question'],
                    'options' => array_values($q['options']),
                    'correct_index' => $q['correct_index'],
                    'explanation' => $q['explanation'],
                ]);
            }

            return $quiz;
        });

        if ($quiz->status === 'draft') {
            Notification::make()->title("Tomorrow's quiz needs approval")
                ->body('One or more source stories are sensitive.')->warning()
                ->sendToDatabase(Admin::all());
        }
    }
}
