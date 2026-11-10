<?php

namespace App\Services\Translation;

/**
 * Development driver: marks text with the target language so the full review flow
 * can be tested without an API key. Never use in production.
 */
class FakeDriver implements TranslationDriver
{
    public function translate(array $fields, string $from, string $to, array $glossary, string $style): array
    {
        $out = [];
        foreach ($fields as $k => $v) {
            $out[$k] = $v === '' ? '' : (str_contains($v, '<') ? preg_replace('/>([^<]+)</u', '>['.strtoupper($to).'] $1<', $v) : '['.strtoupper($to).'] '.$v);
        }

        return $out;
    }

    public function name(): string
    {
        return 'fake';
    }
}
