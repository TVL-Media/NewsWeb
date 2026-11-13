<?php

namespace App\Services;

use App\Exceptions\AiWriterException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Claude API (Messages, raw REST through Laravel's HTTP client).
 *
 * - Strict JSON via structured outputs: output_config.format = json_schema.
 * - Refusal fallbacks: fallbacks "default" (beta server-side-fallback-2026-07-01) re-runs a
 *   safety-declined request on Anthropic's recommended fallback model inside the same call.
 * - Retries 429 / 5xx / connection errors with backoff; a refusal or max_tokens stop throws.
 * - No ANTHROPIC_API_KEY → seeded sample drafts (database/seeders/data), so everything runs offline.
 */
class AiWriter
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2026-10-21';
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public const RISK_FLAGS = [
        'politics', 'elections', 'crime', 'courts', 'religion', 'communal', 'health_claims',
        'allegations', 'deaths', 'disasters', 'children', 'breaking',
    ];

    public function enabled(): bool
    {
        return filled(config('services.anthropic.key'));
    }

    /**
     * Write a bilingual story from one cluster of source items.
     *
     * @param  Collection  $items  FeedItem models (title, body, url, published_at, feedSource)
     * @param  array{categories: string[], pages: string[], cities: string[]}  $options  allowed slugs
     * @return array draft matching draftSchema()
     */
    public function draft(Collection $items, array $options): array
    {
        if (! $this->enabled()) {
            return $this->sampleDraft($items, $options);
        }

        $sources = $items->values()->map(fn ($item, $i) => sprintf(
            "<source index=\"%d\" outlet=\"%s\" published=\"%s\" url=\"%s\">\nTITLE: %s\n\n%s\n</source>",
            $i + 1, $item->feedSource?->name, optional($item->published_at)->toIso8601String(), $item->url,
            $item->title, mb_substr(strip_tags((string) $item->body), 0, 12000),
        ))->implode("\n\n");

        $draft = $this->call(
            system: $this->draftSystemPrompt($options),
            user: "Write one NextGenNews story from these source reports about the same event.\n\n{$sources}",
            schema: $this->draftSchema($options),
        );

        return $this->normaliseDraft($draft, $options);
    }

    /**
     * Five multiple-choice questions about the given stories (one per story).
     *
     * @param  Collection  $stories  Story models with versions loaded
     * @return array<int, array{story_id: int, question: string, options: string[], correct_index: int, explanation: string}>
     */
    public function quiz(Collection $stories): array
    {
        if (! $this->enabled()) {
            return $this->sampleQuiz($stories);
        }

        $text = $stories->values()->map(fn ($s, $i) => sprintf(
            "<story id=\"%d\">\nHEADLINE: %s\nSUMMARY: %s\nKEY POINTS:\n- %s\n</story>",
            $s->id, $s->version('en')?->headline, $s->version('en')?->card_text, implode("\n- ", $s->version('en')?->key_points ?? []),
        ))->implode("\n\n");

        $result = $this->call(
            system: "You write the NextGenNews daily news quiz for Indian readers. Write exactly one question per story, "
                ."answerable only from the story text given. Four short options, exactly one correct, plausible distractors, "
                ."no trick questions, no questions about deaths, crime victims or allegations. The explanation is one sentence "
                .'citing the fact from the story.',
            user: "Write the quiz for these stories:\n\n{$text}",
            schema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['questions'],
                'properties' => [
                    'questions' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['story_id', 'question', 'options', 'correct_index', 'explanation'],
                            'properties' => [
                                'story_id' => ['type' => 'integer'],
                                'question' => ['type' => 'string'],
                                'options' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'correct_index' => ['type' => 'integer', 'enum' => [0, 1, 2, 3]],
                                'explanation' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $ids = $stories->pluck('id')->all();

        return collect($result['questions'] ?? [])
            ->filter(fn ($q) => in_array($q['story_id'], $ids, true) && count($q['options']) === 4)
            ->unique('story_id')
            ->values()
            ->all();
    }

    /**
     * Translate story fields between English and Hindi (admin panel → AI translation).
     *
     * @param  array<string,string>  $fields  e.g. headline, card_text, key_point_0…, full_text
     * @param  array<int,array{source_term:string,target_term:?string,do_not_translate:bool}>  $glossary
     * @return array<string,string> the same keys, translated
     */
    public function translate(array $fields, string $from, string $to, array $glossary = [], string $style = ''): array
    {
        $names = ['en' => 'English', 'hi' => 'Hindi (Devanagari script)'];
        $gloss = collect($glossary)->map(fn ($g) => $g['do_not_translate']
            ? "- Keep \"{$g['source_term']}\" as it is"
            : "- \"{$g['source_term']}\" → \"{$g['target_term']}\"")->implode("\n");
        $system = 'You translate Indian news copy from '.($names[$from] ?? $from).' to '.($names[$to] ?? $to).'. '
            .'Keep every fact, name, number, date and direct quote exactly. Do not add or drop information. '
            .'Keep the card text within 60 words. Keep blank lines between paragraphs. '.$style
            .($gloss ? "\nGlossary (must follow):\n{$gloss}" : '');
        $schema = [
            'type' => 'object',
            'properties' => collect($fields)->map(fn () => ['type' => 'string'])->all(),
            'required' => array_keys($fields),
            'additionalProperties' => false,
        ];
        $out = $this->call($system, json_encode($fields, JSON_UNESCAPED_UNICODE), $schema);

        return array_map(fn ($v) => is_string($v) ? $v : '', array_intersect_key($out + $fields, $fields));
    }

    /** One Messages API call with a JSON-schema response format; returns the decoded JSON. */
    private function call(string $system, string $user, array $schema): array
    {
        $body = [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 16000,
            'fallbacks' => 'default',
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
            'output_config' => [
                'effort' => config('services.anthropic.effort', 'medium'),
                'format' => ['type' => 'json_schema', 'schema' => $schema],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => self::API_VERSION,
                'anthropic-beta' => self::FALLBACK_BETA,
            ])
                ->acceptJson()
                ->timeout(180)
                ->retry(3, fn (int $attempt) => $attempt * 3000, function ($e) {
                    if ($e instanceof ConnectionException) {
                        return true;
                    }

                    return $e instanceof RequestException
                        && ($e->response->status() === 429 || $e->response->status() >= 500);
                })
                ->post(self::ENDPOINT, $body);
        } catch (RequestException $e) {
            Log::error('AiWriter request failed', ['status' => $e->response->status(), 'body' => mb_substr($e->response->body(), 0, 2000)]);
            throw new AiWriterException('Claude API error '.$e->response->status().': '.$e->response->json('error.message', 'unknown'), previous: $e);
        } catch (ConnectionException $e) {
            throw new AiWriterException('Could not reach the Claude API: '.$e->getMessage(), previous: $e);
        }

        $data = $response->json();
        $stop = $data['stop_reason'] ?? null;

        if ($stop === 'refusal') {
            $category = $data['stop_details']['category'] ?? 'unspecified';
            throw new AiWriterException("Claude declined this request (category: {$category}). The cluster needs an editor.");
        }
        if ($stop === 'max_tokens') {
            throw new AiWriterException('Claude hit max_tokens before finishing the draft.');
        }

        $text = collect($data['content'] ?? [])->where('type', 'text')->pluck('text')->implode('');
        $json = json_decode($text, true);

        if (! is_array($json)) {
            throw new AiWriterException('Claude returned output that is not valid JSON.');
        }

        return $json;
    }

    private function draftSystemPrompt(array $options): string
    {
        return <<<PROMPT
        You are a senior editor at NextGenNews, an Indian news service that publishes short, accurate
        news cards in English and Hindi for readers on their phones.

        Write the story in our own words. Never copy a sentence or a distinctive phrase from the sources:
        we only license the facts, not the wording. Use only facts that appear in the sources; do not add
        background you cannot see there, and do not speculate. Keep every name, number and date exactly
        as the sources give them. Attribute claims ("police said", "the ministry said").

        Fields:
        - headline: 8–14 words, plain and specific, no clickbait or question headlines.
        - card_text: 50–60 words, never more than 60. The whole story at a glance.
        - key_points: exactly 3 one-sentence points.
        - why_it_matters: one sentence on why an Indian reader should care.
        - simple_text: 60–90 words a 12-year-old understands.
        - full_text: 3–5 short paragraphs (170–260 words), separated by blank lines.
        - hi: the same six fields in natural, fluent Hindi (Devanagari) as an Indian Hindi news desk
          writes it, not a word-for-word translation. Keep names, numbers and dates identical.
        - category: the best fit from the allowed list. page / city: a slug from the allowed lists, or "" if none fits.
        - importance: 1–10 for a general Indian audience (10 = the day's biggest story).
        - risk_flags: every flag that applies (politics, elections, crime, courts, religion, communal,
          health_claims, allegations, deaths, disasters, children, breaking). Use [] only when none apply.
        - confidence: 0–1, how well the sources agree and support every fact you wrote.

        Use Indian English conventions (lakh, crore).
        PROMPT;
    }

    private function draftSchema(array $options): array
    {
        $version = [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['headline', 'card_text', 'key_points', 'why_it_matters', 'simple_text', 'full_text'],
            'properties' => [
                'headline' => ['type' => 'string'],
                'card_text' => ['type' => 'string'],
                'key_points' => ['type' => 'array', 'items' => ['type' => 'string']],
                'why_it_matters' => ['type' => 'string'],
                'simple_text' => ['type' => 'string'],
                'full_text' => ['type' => 'string'],
            ],
        ];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['en', 'hi', 'category', 'page', 'city', 'importance', 'risk_flags', 'confidence'],
            'properties' => [
                'en' => $version,
                'hi' => $version,
                'category' => ['type' => 'string', 'enum' => array_values($options['categories'])],
                'page' => ['type' => 'string', 'enum' => array_values(['', ...$options['pages']])],
                'city' => ['type' => 'string', 'enum' => array_values(['', ...$options['cities']])],
                'importance' => ['type' => 'integer'],
                'risk_flags' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => self::RISK_FLAGS]],
                'confidence' => ['type' => 'number'],
            ],
        ];
    }

    /** Enforce the limits JSON Schema can't express (3 key points, ranges). */
    private function normaliseDraft(array $draft, array $options): array
    {
        foreach (['en', 'hi'] as $lang) {
            $points = array_values(array_filter(array_map('trim', $draft[$lang]['key_points'] ?? [])));
            if (count($points) < 3) {
                throw new AiWriterException("The {$lang} draft has fewer than 3 key points.");
            }
            $draft[$lang]['key_points'] = array_slice($points, 0, 3);
        }

        $draft['importance'] = max(1, min(10, (int) ($draft['importance'] ?? 5)));
        $draft['confidence'] = max(0.0, min(1.0, (float) ($draft['confidence'] ?? 0)));
        $draft['risk_flags'] = array_values(array_intersect($draft['risk_flags'] ?? [], self::RISK_FLAGS));
        if (! in_array($draft['category'] ?? null, $options['categories'], true)) {
            $draft['category'] = $options['categories'][0];
        }

        return $draft;
    }

    /**
     * Offline mode: a seeded sample draft (rotated by the source title), so the pipeline can be
     * demonstrated without an API key. Checks will usually route these to the approval queue,
     * because the facts won't match the real source.
     */
    private function sampleDraft(Collection $items, array $options): array
    {
        $samples = array_merge(
            require database_path('seeders/data/stories_1.php'),
            require database_path('seeders/data/stories_2.php'),
            require database_path('seeders/data/stories_3.php'),
        );
        $sample = $samples[crc32((string) $items->first()?->title) % count($samples)];

        return [
            'en' => $sample['en'],
            'hi' => $sample['hi'],
            'category' => in_array($sample['category'], $options['categories'], true) ? $sample['category'] : $options['categories'][0],
            'page' => in_array($sample['page'], $options['pages'], true) ? $sample['page'] : '',
            'city' => in_array((string) $sample['city'], $options['cities'], true) ? (string) $sample['city'] : '',
            'importance' => $sample['importance'],
            'risk_flags' => $sample['is_breaking'] ? ['breaking'] : [],
            'confidence' => 0.6,
            'sample' => true,
        ];
    }

    /** Offline mode: "Which page covered this story?" questions built from real stories. */
    private function sampleQuiz(Collection $stories): array
    {
        $pages = \App\Models\Page::pluck('name')->all();

        return $stories->values()->map(function ($story, $i) use ($pages) {
            $right = $story->page?->name ?? $pages[0];
            $wrong = collect($pages)->reject(fn ($p) => $p === $right)->values()->slice($i, 3)->pad(3, 'World Brief')->values();
            $options = $wrong->all();
            $correct = $story->id % 4;
            array_splice($options, $correct, 0, [$right]);

            return [
                'story_id' => $story->id,
                'question' => 'Which NextGenNews page covered: “'.$story->version('en')?->headline.'”?',
                'options' => array_slice($options, 0, 4),
                'correct_index' => $correct,
                'explanation' => (string) $story->version('en')?->why_it_matters,
            ];
        })->all();
    }
}
