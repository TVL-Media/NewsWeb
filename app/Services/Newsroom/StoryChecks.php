<?php

namespace App\Services\Newsroom;

use App\Models\AutoPublishSetting;
use App\Models\Story;
use Illuminate\Support\Collection;

/**
 * Automated checks on an AI draft, compared with its source items:
 * - fact check: names, numbers and dates in the English draft must appear in the sources
 * - copy check: share of the draft's 5-word sequences that also appear in the sources (copy_score)
 * - sensitive check: sensitive category, keyword match, or any AI risk flag
 * - confidence: the AI's confidence, reduced for fact mismatches and heavy copying
 */
class StoryChecks
{
    public const SHINGLE = 5;

    private const MONTHS = 'January|February|March|April|May|June|July|August|September|October|November|December';
    private const DAYS = 'Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday';

    /** Capitalised words that are fine without a source match (sentence starters, our own names). */
    private const ALLOWED_NAMES = ['NextGenNews', 'India', 'Indian', 'The', 'This', 'It', 'A', 'An', 'In', 'On', 'At', 'But', 'And', 'He', 'She', 'They', 'Officials', 'Explain'];

    /**
     * @param  Collection  $sources  FeedItem models
     * @return array{risk_level: string, sensitive_reasons: array, confidence: float, fact_mismatches: array, copy_score: float, passed: bool, reasons: array}
     */
    public function run(Story $story, Collection $sources, array $aiRiskFlags, float $aiConfidence, bool $trustedSources): array
    {
        $settings = AutoPublishSetting::current();
        $en = $story->version('en');
        $hi = $story->version('hi');

        $draftEn = implode("\n", array_filter([$en?->headline, $en?->card_text, implode("\n", $en?->key_points ?? []), $en?->why_it_matters, $en?->full_text]));
        $draftAll = $draftEn."\n".implode("\n", array_filter([$hi?->headline, $hi?->card_text, $hi?->full_text]));
        $sourceText = $sources->map(fn ($s) => $s->title."\n".strip_tags((string) $s->body))->implode("\n\n");

        $mismatches = $this->factMismatches($draftEn, $sourceText);
        $copy = $this->copyScore($en?->full_text."\n".$en?->card_text, $sourceText);

        // Sensitive check
        $reasons = [];
        if (in_array($story->category?->slug, $settings->sensitive_categories ?? [], true)) {
            $reasons[] = 'category: '.$story->category->name_en;
        }
        foreach ($this->keywordHits($draftAll."\n".$sourceText, $settings->sensitive_keywords ?? []) as $kw) {
            $reasons[] = 'keyword: '.$kw;
        }
        foreach ($aiRiskFlags as $flag) {
            $reasons[] = 'AI flag: '.str_replace('_', ' ', $flag);
        }
        if ($story->is_breaking && ! in_array('breaking', $aiRiskFlags, true)) {
            $reasons[] = 'breaking news';
        }

        $confidence = max(0.0, min(1.0, $aiConfidence - 0.15 * min(3, count($mismatches)) - ($copy > $settings->copy_threshold ? 0.3 : 0.0)));
        $risk = $reasons ? 'sensitive' : 'low';

        // Why it can't auto-publish (empty = auto-publish)
        $blockers = [];
        if ($risk === 'sensitive') {
            $blockers[] = 'Sensitive: '.implode(', ', $reasons);
        }
        if ($mismatches) {
            $blockers[] = count($mismatches).' fact(s) not found in the sources';
        }
        if ($copy >= $settings->copy_threshold) {
            $blockers[] = sprintf('Copy score %.2f ≥ %.2f', $copy, $settings->copy_threshold);
        }
        if ($confidence < $settings->confidence_threshold) {
            $blockers[] = sprintf('Confidence %.2f < %.2f', $confidence, $settings->confidence_threshold);
        }
        if (! $trustedSources) {
            $blockers[] = 'Source feed is not marked trusted';
        }

        return [
            'risk_level' => $risk,
            'sensitive_reasons' => array_values(array_unique($reasons)),
            'confidence' => round($confidence, 3),
            'fact_mismatches' => $mismatches,
            'copy_score' => round($copy, 3),
            'passed' => ! $blockers,
            'reasons' => $blockers,
        ];
    }

    /** Names, numbers and dates in the draft that the sources don't contain. */
    public function factMismatches(string $draft, string $source): array
    {
        $normSource = mb_strtolower(str_replace(',', '', $source));
        $missing = [];

        // Numbers (1,200 → 1200; 4.5; 2026)
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $draft, $m);
        foreach (array_unique($m[0]) as $num) {
            $plain = str_replace(',', '', rtrim($num, '.'));
            if (! preg_match('/(?<![\d.])'.preg_quote($plain, '/').'(?![\d])/', $normSource)) {
                $missing[] = ['type' => 'number', 'value' => $num];
            }
        }

        // Dates: months and weekdays
        preg_match_all('/\b(?:'.self::MONTHS.'|'.self::DAYS.')\b/', $draft, $m);
        foreach (array_unique($m[0]) as $word) {
            if (! str_contains($normSource, mb_strtolower($word))) {
                $missing[] = ['type' => 'date', 'value' => $word];
            }
        }

        // Names: runs of 2+ capitalised words (e.g. "Panama Canal Authority")
        preg_match_all('/\b[A-Z][a-zA-Z\-]+(?:\s+[A-Z][a-zA-Z\-]+)+\b/', $draft, $m);
        foreach (array_unique($m[0]) as $name) {
            $words = array_filter(explode(' ', preg_replace('/\s+/', ' ', $name)), fn ($w) => ! in_array($w, self::ALLOWED_NAMES, true)
                && ! preg_match('/^(?:'.self::MONTHS.'|'.self::DAYS.')$/', $w));
            if (! $words) {
                continue;
            }
            $phrase = mb_strtolower(implode(' ', $words));
            if (! str_contains($normSource, $phrase)) {
                $missing[] = ['type' => 'name', 'value' => implode(' ', $words)];
            }
        }

        return array_values(array_slice($missing, 0, 20));
    }

    /** Share (0–1) of the draft's 5-word sequences that also occur in the source text. */
    public function copyScore(string $draft, string $source): float
    {
        $a = $this->shingles($draft);
        if (! $a) {
            return 0.0;
        }

        return count(array_intersect_key($a, $this->shingles($source))) / count($a);
    }

    private function shingles(string $text): array
    {
        preg_match_all('/[\p{L}\p{M}\p{N}]+/u', mb_strtolower($text), $m);
        $words = $m[0];
        $out = [];
        for ($i = 0; $i + self::SHINGLE <= count($words); $i++) {
            $out[implode(' ', array_slice($words, $i, self::SHINGLE))] = true;
        }

        return $out;
    }

    private function keywordHits(string $text, array $keywords): array
    {
        $lower = mb_strtolower($text);

        return array_values(array_filter($keywords, function ($kw) use ($lower) {
            $kw = mb_strtolower(trim((string) $kw));
            if ($kw === '') {
                return false;
            }

            return preg_match('/^[\p{Latin}\s\-]+$/u', $kw)
                ? (bool) preg_match('/\b'.preg_quote($kw, '/').'\b/u', $lower)
                : str_contains($lower, $kw);
        }));
    }
}
