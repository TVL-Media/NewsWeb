<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BuildBriefingAudio;
use App\Jobs\DraftQuiz;
use App\Jobs\PickMorning5;
use App\Models\Briefing;
use App\Models\Quiz;
use App\Models\Story;
use App\Support\Audit;
use App\Support\PageCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Morning 5 briefings and the daily quiz. */
class DailyController extends Controller
{
    use Concerns;

    private function today(): string
    {
        return now(config('app.timezone'))->toDateString();
    }

    private function recentStories()
    {
        return Story::published()->with('versions:id,story_id,language,headline')->where('published_at', '>=', now()->subDays(3))
            ->latest('published_at')->get()->mapWithKeys(fn ($s) => [$s->id => $s->title()]);
    }

    // ── Morning 5 ───────────────────────────────────────────────────────────

    public function briefings()
    {
        $items = Briefing::orderByDesc('date')->paginate(30);
        $ids = $items->pluck('story_ids')->flatten()->unique();
        $titles = Story::with('versions:id,story_id,language,headline')->whereIn('id', $ids)->get()->mapWithKeys(fn ($s) => [$s->id => $s->title()]);

        return view('admin.daily.briefings', ['items' => $items, 'titles' => $titles, 'today' => $this->today()]);
    }

    public function briefingEdit(?Briefing $briefing = null)
    {
        $briefing ??= new Briefing(['date' => $this->today(), 'story_ids' => []]);

        return view('admin.daily.briefing-edit', ['b' => $briefing, 'stories' => $this->recentStories()]);
    }

    public function briefingSave(Request $r, ?Briefing $briefing = null)
    {
        $data = $r->validate([
            'date' => ['required', 'date', Rule::unique('briefings', 'date')->ignore($briefing?->id)],
            'story_ids' => 'required|array|size:5', 'story_ids.*' => 'required|integer|distinct|exists:stories,id',
        ], ['story_ids.size' => 'Pick exactly five stories.', 'story_ids.*.distinct' => 'Each story can be used once.']);
        $data['story_ids'] = array_map('intval', array_values($data['story_ids']));
        $before = $briefing?->only('date', 'story_ids');
        $briefing ??= new Briefing;
        $briefing->fill($data)->save();
        BuildBriefingAudio::dispatch($briefing->id);
        PageCache::flush();
        Audit::log('briefing.saved', $briefing, $before, $briefing->only('date', 'story_ids'), null, 'Morning 5 · '.$briefing->date->format('j M Y'));

        return redirect()->route('admin.daily.briefings')->with('ok', 'Morning 5 saved. The audio is being rebuilt.');
    }

    public function briefingAudio(Briefing $briefing)
    {
        BuildBriefingAudio::dispatch($briefing->id);

        return back()->with('ok', 'Rebuilding the Morning 5 audio.');
    }

    /** Run the automatic pick for today (only when today has no briefing yet). */
    public function briefingPick()
    {
        if (Briefing::whereDate('date', $this->today())->exists()) {
            return back()->with('error', 'Today already has a Morning 5. Edit it instead.');
        }
        PickMorning5::dispatch();

        return back()->with('ok', 'Picking today’s Morning 5 from the last 24 hours.');
    }

    // ── Daily quiz ──────────────────────────────────────────────────────────

    public function quizzes()
    {
        return view('admin.daily.quizzes', ['items' => Quiz::withCount(['questions', 'attempts'])->orderByDesc('date')->paginate(30), 'today' => $this->today()]);
    }

    public function quizEdit(?Quiz $quiz = null)
    {
        $quiz ??= new Quiz(['date' => now(config('app.timezone'))->addDay()->toDateString(), 'status' => 'draft']);
        $questions = $quiz->exists ? $quiz->questions()->orderBy('sort_order')->get() : collect();

        return view('admin.daily.quiz-edit', ['q' => $quiz, 'questions' => $questions, 'stories' => $this->recentStories()]);
    }

    public function quizSave(Request $r, ?Quiz $quiz = null)
    {
        $data = $r->validate([
            'date' => ['required', 'date', Rule::unique('quizzes', 'date')->ignore($quiz?->id)],
            'status' => 'required|in:draft,approved,published',
            'questions' => 'required|array|min:1|max:5',
            'questions.*.question' => 'required|string|max:255',
            'questions.*.options' => 'required|array|size:4', 'questions.*.options.*' => 'required|string|max:120',
            'questions.*.correct_index' => 'required|integer|between:0,3',
            'questions.*.explanation' => 'nullable|string|max:1000',
            'questions.*.story_id' => 'nullable|integer|exists:stories,id',
        ]);
        $played = $quiz?->exists && $quiz->attempts()->exists();
        if ($played && count($data['questions']) !== $quiz->questions()->count()) {
            return back()->withInput()->with('error', 'Readers already played this quiz: you can fix the wording, but not add or remove questions.');
        }
        DB::transaction(function () use ($data, &$quiz) {
            $quiz ??= new Quiz;
            $quiz->fill(['date' => $data['date'], 'status' => $data['status']])->save();
            // Update questions in place (quiz_attempts.answers are keyed by question id).
            $existing = $quiz->questions()->orderBy('sort_order')->get()->values();
            foreach (array_values($data['questions']) as $i => $q) {
                $row = [
                    'question' => $q['question'], 'options' => array_values($q['options']), 'correct_index' => (int) $q['correct_index'],
                    'explanation' => $q['explanation'] ?? null, 'story_id' => $q['story_id'] ?? null, 'sort_order' => $i,
                ];
                isset($existing[$i]) ? $existing[$i]->update($row) : $quiz->questions()->create($row);
            }
            $existing->slice(count($data['questions']))->each->delete();
        });
        Audit::log('quiz.saved', $quiz, null, ['date' => $data['date'], 'status' => $data['status'], 'questions' => count($data['questions'])], null, 'Quiz · '.$quiz->date->format('j M Y'));

        return redirect()->route('admin.daily.quizzes')->with('ok', 'Quiz saved.');
    }

    public function quizApprove(Quiz $quiz)
    {
        $quiz->update(['status' => 'approved']);
        Audit::log('quiz.approved', $quiz, ['status' => 'draft'], ['status' => 'approved'], null, 'Quiz · '.$quiz->date->format('j M Y'));

        return back()->with('ok', 'Quiz approved. Readers can play it on its day.');
    }

    public function quizDraft()
    {
        DraftQuiz::dispatch();

        return back()->with('ok', 'Drafting tomorrow’s quiz from today’s top stories.');
    }

    public function quizDestroy(Quiz $quiz)
    {
        abort_if($quiz->attempts()->exists(), 422, 'Readers already played this quiz, so it can’t be deleted.');
        $quiz->delete();
        Audit::log('quiz.deleted', null, null, null, null, 'Quiz · '.$quiz->date->format('j M Y'));

        return back()->with('ok', 'Quiz deleted.');
    }
}
