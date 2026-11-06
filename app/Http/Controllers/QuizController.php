<?php

namespace App\Http\Controllers;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\ShareImage;
use App\Services\StreakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daily quiz (/quiz). Correct answers never reach the browser before the reader answers:
 * each answer is checked by POST /quiz/answers, which returns the result and explanation.
 */
class QuizController extends Controller
{
    public const SECONDS_PER_QUESTION = 30;

    public function show(Request $request)
    {
        $quiz = $this->todaysQuiz();
        if (! $quiz) {
            return view('quiz', ['quiz' => null]);
        }

        $quiz->load(['questions.story.category', 'questions.story.versions']);
        $attempt = QuizAttempt::where('user_id', $request->user()->id)->where('quiz_id', $quiz->id)->first();

        return view('quiz', [
            'quiz' => $quiz,
            'questions' => $quiz->questions->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'options' => $q->options,
                'tag' => $q->story?->category?->name(),
                'photo' => $q->story?->photoUrl(),
                'story_url' => $q->story ? route('story.show', $q->story) : null,
            ])->values(),
            'answered' => $attempt?->answers ?? [],
            'done' => $attempt?->completed_at ? ['score' => $attempt->score, 'total' => $quiz->questions->count()] : null,
            'streak' => (int) ($request->user()->streak?->current ?? 0),
            'seconds' => self::SECONDS_PER_QUESTION,
        ]);
    }

    // POST /quiz/answers  { question_id, choice: 0–3 | null (timed out) }
    public function answer(Request $request, StreakService $streaks): JsonResponse
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'choice' => ['nullable', 'integer', 'between:0,3'],
        ]);

        $quiz = $this->todaysQuiz();
        abort_unless($quiz, 404);
        $question = $quiz->questions()->whereKey($data['question_id'])->firstOrFail();
        $user = $request->user();

        $attempt = QuizAttempt::firstOrCreate(['user_id' => $user->id, 'quiz_id' => $quiz->id], ['answers' => []]);
        $answers = $attempt->answers ?? [];

        // Answers can't be changed once given.
        if (! array_key_exists((string) $question->id, $answers) && ! $attempt->completed_at) {
            $answers[(string) $question->id] = $data['choice'];
            $attempt->answers = $answers;
        }
        $choice = $answers[(string) $question->id];

        $total = $quiz->questions()->count();
        $result = [
            'correct' => $choice !== null && (int) $choice === $question->correct_index,
            'correct_index' => $question->correct_index,
            'explanation' => $question->explanation,
            'done' => false,
        ];

        if (count($answers) >= $total && ! $attempt->completed_at) {
            $correct = $quiz->questions()->get()->filter(fn ($q) => isset($answers[(string) $q->id]) && (int) $answers[(string) $q->id] === $q->correct_index)->count();
            $attempt->score = $correct;
            $attempt->completed_at = now();
            $attempt->save();

            [$streak, $increased] = $streaks->recordActivity($user);
            $result = array_merge($result, ['done' => true, 'score' => $correct, 'total' => $total, 'streak' => $streak->current, 'streak_increased' => $increased]);
        } else {
            $attempt->save();
        }

        return response()->json($result);
    }

    // POST /quiz/share → result image (1080×1350)
    public function share(Request $request, ShareImage $images): JsonResponse
    {
        $quiz = $this->todaysQuiz();
        $attempt = $quiz ? QuizAttempt::where('user_id', $request->user()->id)->where('quiz_id', $quiz->id)->whereNotNull('completed_at')->first() : null;
        abort_unless($attempt, 404);

        return response()->json([
            'image_url' => $images->quizResultUrl($attempt, $quiz->questions()->count(), (int) ($request->user()->streak?->current ?? 0)),
            'url' => route('quiz'),
        ]);
    }

    private function todaysQuiz(): ?Quiz
    {
        $quiz = Quiz::today();

        return $quiz && in_array($quiz->status, ['approved', 'published'], true) ? $quiz : null;
    }
}
