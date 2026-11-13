<?php

namespace App\Services\Translation;

use App\Jobs\TranslateStory;
use App\Models\GlossaryTerm;
use App\Models\Story;
use App\Models\TranslationJob;
use App\Services\AiWriter;
use App\Services\Newsroom\StoryPublisher;
use App\Support\Audit;
use App\Support\Notify;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * AI translation of a story between English and Hindi.
 * The AI text waits in translation_jobs.result until an editor approves it, so readers never
 * see an unreviewed machine translation. Approving writes the story_versions row.
 */
class TranslationManager
{
    public const FIELDS = ['headline', 'card_text', 'key_point_0', 'key_point_1', 'key_point_2', 'why_it_matters', 'simple_text', 'full_text'];

    public function driver(): TranslationDriver
    {
        $p = (string) setting('translation.provider', 'auto');
        if ($p === 'auto') {
            $p = app(AiWriter::class)->enabled() ? 'claude' : 'fake';
        }

        return match ($p) {
            'claude' => new NewsroomDriver(app(AiWriter::class)),
            'anthropic', 'openai' => new LlmDriver($p, (string) setting('translation.api_key', ''), (string) setting('translation.model', '')),
            default => new FakeDriver,
        };
    }

    public function monthlyCharsUsed(): int
    {
        return (int) TranslationJob::where('created_at', '>=', now()->startOfMonth())->whereNotIn('status', ['failed', 'rejected'])->sum('characters');
    }

    /** Source fields as a flat array (key points split out so the model sees plain strings). */
    public static function fieldsOf(?\App\Models\StoryVersion $v): array
    {
        if (! $v) {
            return [];
        }
        $out = ['headline' => $v->headline, 'card_text' => $v->card_text];
        foreach (array_values($v->key_points ?? []) as $i => $p) {
            $out["key_point_{$i}"] = $p;
        }
        $out += ['why_it_matters' => $v->why_it_matters, 'simple_text' => $v->simple_text, 'full_text' => $v->full_text];

        return array_filter(array_map(fn ($x) => (string) $x, $out), fn ($x) => $x !== '');
    }

    public function request(Story $story, string $target, ?int $adminId): TranslationJob
    {
        abort_unless(in_array($target, ['en', 'hi'], true), 422);
        $source = $target === 'hi' ? 'en' : 'hi';
        $fields = self::fieldsOf($story->versions()->where('language', $source)->first());
        if (! $fields) {
            throw ValidationException::withMessages(['translate' => 'Write the '.($source === 'en' ? 'English' : 'Hindi').' version first.']);
        }
        $chars = mb_strlen(implode('', $fields));
        $limit = (int) setting('translation.monthly_char_limit', 0);
        if ($limit > 0 && $this->monthlyCharsUsed() + $chars > $limit) {
            Notify::superAdmins('translation', 'Translation monthly limit reached', 'New translation requests are blocked until next month or the limit is raised.');
            throw ValidationException::withMessages(['translate' => 'Monthly translation limit reached. Raise it in Translations → Settings.']);
        }
        // One open job per story and language.
        TranslationJob::where('story_id', $story->id)->where('target_language', $target)->whereIn('status', ['queued', 'running', 'ai_completed', 'failed'])->update(['status' => 'rejected', 'error' => 'Replaced by a newer request']);

        $job = TranslationJob::create([
            'story_id' => $story->id, 'source_language' => $source, 'target_language' => $target,
            'provider' => $this->driver()->name(), 'status' => 'queued', 'characters' => $chars, 'requested_by' => $adminId,
        ]);
        Audit::log('translation.requested', $story, null, ['language' => $target]);
        TranslateStory::dispatch($job->id);

        return $job->refresh();
    }

    public function process(TranslationJob $job): void
    {
        $job->update(['status' => 'running']);
        try {
            $fields = self::fieldsOf($job->story->versions()->where('language', $job->source_language)->first());
            $out = $this->driver()->translate($fields, $job->source_language, $job->target_language, $this->glossary($job->target_language), (string) setting('translation.style_prompt'));
            $job->update(['status' => 'ai_completed', 'result' => $out, 'error' => null]);
            Notify::permission('translation.review', 'translation', 'Translation ready for review',
                Str::limit($job->story->title(), 100).' → '.TranslationJob::LANGS[$job->target_language], route('admin.translations.review', $job));
        } catch (\Throwable $e) {
            $job->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)]);
        }
    }

    public function glossary(string $lang): array
    {
        return GlossaryTerm::where('language_code', $lang)->get()->map(fn ($g) => $g->only('source_term', 'target_term', 'do_not_translate'))->all();
    }

    /** Re-run one field (review screen). */
    public function retranslateField(TranslationJob $job, string $field): string
    {
        $src = self::fieldsOf($job->story->versions()->where('language', $job->source_language)->first());

        return $this->driver()->translate([$field => $src[$field] ?? ''], $job->source_language, $job->target_language, $this->glossary($job->target_language), (string) setting('translation.style_prompt'))[$field] ?? '';
    }

    /** Write the reviewed text into the story. Live stories refresh their audio and cached pages. */
    public function approve(TranslationJob $job, int $adminId): void
    {
        $r = $job->result ?? [];
        $story = $job->story;
        $story->versions()->updateOrCreate(['language' => $job->target_language], [
            'headline' => (string) ($r['headline'] ?? ''),
            'card_text' => (string) ($r['card_text'] ?? ''),
            'key_points' => array_values(array_filter([$r['key_point_0'] ?? '', $r['key_point_1'] ?? '', $r['key_point_2'] ?? ''], 'filled')),
            'why_it_matters' => $r['why_it_matters'] ?? null,
            'simple_text' => $r['simple_text'] ?? null,
            'full_text' => $r['full_text'] ?? null,
            'ai_assisted' => true,
            'translation_status' => 'approved',
        ]);
        $job->update(['status' => 'approved', 'reviewed_by' => $adminId, 'reviewed_at' => now()]);
        if ($story->isLive()) {
            app(StoryPublisher::class)->refreshLive($story);
        }
        Audit::log('translation.approved', $story, null, ['language' => $job->target_language]);
    }
}
