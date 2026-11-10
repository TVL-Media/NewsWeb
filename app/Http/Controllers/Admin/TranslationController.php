<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\TranslateStory;
use App\Models\GlossaryTerm;
use App\Models\Story;
use App\Models\TranslationJob;
use App\Services\AiWriter;
use App\Services\Translation\TranslationManager;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;

/** AI translation English ↔ Hindi: queue, side-by-side review, glossary, settings. */
class TranslationController extends Controller
{
    use Concerns;

    public function __construct(private TranslationManager $tm) {}

    public function index(Request $r)
    {
        $q = TranslationJob::with(['story.versions:id,story_id,language,headline,updated_at', 'requester:id,name', 'reviewer:id,name'])->latest();
        if ($s = $r->query('status')) {
            $q->where('status', $s);
        }
        if ($l = $r->query('language')) {
            $q->where('target_language', $l);
        }
        $counts = TranslationJob::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $missingHi = Story::whereIn('status', ['published', 'corrected'])->where('published_at', '>=', now()->subDays(2))
            ->whereDoesntHave('versions', fn ($v) => $v->where('language', 'hi'))->count();

        return view('admin.translations.index', [
            'jobs' => $q->paginate($this->perPage($r))->withQueryString(), 'counts' => $counts,
            'used' => $this->tm->monthlyCharsUsed(), 'limit' => (int) setting('translation.monthly_char_limit', 0),
            'provider' => $this->tm->driver()->name(), 'missingHi' => $missingHi,
        ]);
    }

    public function request(Request $r, Story $story)
    {
        $data = $r->validate(['language' => 'required|in:en,hi']);
        $job = $this->tm->request($story, $data['language'], auth('admin')->id());
        $msg = match ($job->status) {
            'failed' => 'Translation failed: '.$job->error,
            'ai_completed' => 'Translation ready for review.',
            default => 'Translation queued. You’ll get a notification when it is ready.',
        };

        return $job->status === 'ai_completed'
            ? redirect()->route('admin.translations.review', $job)->with('ok', $msg)
            : back()->with($job->status === 'failed' ? 'error' : 'ok', $msg);
    }

    /** Fill in Hindi for recent live stories that have none (up to 20 at a time). */
    public function backfill()
    {
        $this->need('translation.request');
        $n = 0;
        Story::whereIn('status', ['published', 'corrected'])->where('published_at', '>=', now()->subDays(2))
            ->whereDoesntHave('versions', fn ($v) => $v->where('language', 'hi'))
            ->whereDoesntHave('translationJobs', fn ($j) => $j->where('target_language', 'hi')->whereIn('status', ['queued', 'running', 'ai_completed']))
            ->latest('published_at')->limit(20)->get()
            ->each(function (Story $s) use (&$n) {
                try {
                    $this->tm->request($s, 'hi', auth('admin')->id());
                    $n++;
                } catch (\Throwable) {
                }
            });

        return back()->with('ok', "{$n} Hindi translations requested.");
    }

    public function review(TranslationJob $job)
    {
        $job->load('story.versions');
        $src = TranslationManager::fieldsOf($job->story->versions->firstWhere('language', $job->source_language));
        $current = TranslationManager::fieldsOf($job->story->versions->firstWhere('language', $job->target_language));

        return view('admin.translations.review', ['job' => $job, 's' => $job->story, 'src' => $src, 'current' => $current, 'glossary' => GlossaryTerm::where('language_code', $job->target_language)->get()]);
    }

    public function save(Request $r, TranslationJob $job)
    {
        abort_unless($job->status === 'ai_completed', 422, 'Only translations waiting for review can be edited here.');
        $data = $r->validate(['f' => 'required|array', 'f.headline' => 'required|string|max:250', 'f.card_text' => 'required|string|max:2500', 'f.*' => 'nullable|string|max:90000']);
        $result = array_intersect_key($data['f'], array_flip(TranslationManager::FIELDS));
        $words = count(preg_split('/\s+/u', trim($result['card_text'])));
        if ($words > 60) {
            return back()->withInput()->withErrors(['f.card_text' => "The card text has {$words} words. Keep it to 60."]);
        }
        $job->update(['result' => $result]);
        if ($r->input('then') === 'approve') {
            return $this->approve($job);
        }

        return back()->with('ok', 'Saved.');
    }

    public function approve(TranslationJob $job)
    {
        abort_unless($job->status === 'ai_completed', 422);
        $this->tm->approve($job, auth('admin')->id());

        return redirect()->route('admin.stories.edit', $job->story_id)->with('ok', TranslationJob::LANGS[$job->target_language].' version saved to the story'.($job->story->isLive() ? ' and live on the website.' : '.'));
    }

    public function retranslateField(Request $r, TranslationJob $job)
    {
        $field = $r->validate(['field' => 'required|in:'.implode(',', TranslationManager::FIELDS)])['field'];
        try {
            return response()->json(['text' => $this->tm->retranslateField($job, $field)]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function retry(TranslationJob $job)
    {
        $job->update(['status' => 'queued', 'error' => null]);
        TranslateStory::dispatch($job->id);

        return back()->with('ok', 'Retry queued.');
    }

    public function reject(TranslationJob $job)
    {
        $job->update(['status' => 'rejected', 'reviewed_by' => auth('admin')->id(), 'reviewed_at' => now()]);
        Audit::log('translation.rejected', $job->story, null, ['language' => $job->target_language]);

        return redirect()->route('admin.translations.index')->with('ok', 'Translation discarded. The story was not changed.');
    }

    public function settings()
    {
        return view('admin.translations.settings', ['used' => $this->tm->monthlyCharsUsed(), 'aiReady' => app(AiWriter::class)->enabled()]);
    }

    public function saveSettings(Request $r)
    {
        $data = $r->validate([
            'provider' => 'required|in:auto,claude,fake,anthropic,openai', 'model' => 'nullable|string|max:80', 'api_key' => 'nullable|string|max:300', 'base_url' => 'nullable|url',
            'style_prompt' => 'nullable|string|max:1000', 'monthly_char_limit' => 'nullable|integer|min:0',
        ]);
        $before = ['provider' => setting('translation.provider')];
        Settings::set('translation.provider', $data['provider'], auth('admin')->id());
        Settings::set('translation.model', $data['model'] ?? '');
        if (! empty($data['api_key'])) {
            Settings::set('translation.api_key', $data['api_key']);
        }
        Settings::set('translation.base_url', $data['base_url'] ?? null);
        Settings::set('translation.style_prompt', $data['style_prompt'] ?? '');
        Settings::set('translation.monthly_char_limit', (int) ($data['monthly_char_limit'] ?? 0));
        Audit::log('settings.translation_updated', null, $before, ['provider' => $data['provider']], null, 'Translation settings');

        return back()->with('ok', 'Translation settings saved.');
    }

    public function glossary(Request $r)
    {
        $q = GlossaryTerm::query()->orderBy('language_code')->orderBy('source_term');
        if ($l = $r->query('language')) {
            $q->where('language_code', $l);
        }

        return view('admin.translations.glossary', ['terms' => $q->paginate(50)->withQueryString()]);
    }

    public function glossaryStore(Request $r)
    {
        $data = $r->validate(['language_code' => 'required|in:en,hi', 'source_term' => 'required|string|max:120', 'target_term' => 'nullable|string|max:120|required_without:do_not_translate', 'do_not_translate' => 'nullable|boolean']);
        $data['do_not_translate'] = $r->boolean('do_not_translate');
        GlossaryTerm::create($data);

        return back()->with('ok', 'Glossary term added.');
    }

    public function glossaryDestroy(GlossaryTerm $term)
    {
        $term->delete();

        return back()->with('ok', 'Term removed.');
    }
}
