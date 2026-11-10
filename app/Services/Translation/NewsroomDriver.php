<?php

namespace App\Services\Translation;

use App\Services\AiWriter;

/** Uses the newsroom's own Claude setup (ANTHROPIC_API_KEY / ANTHROPIC_MODEL in .env). */
class NewsroomDriver implements TranslationDriver
{
    public function __construct(private AiWriter $ai) {}

    public function translate(array $fields, string $from, string $to, array $glossary, string $style): array
    {
        return $this->ai->translate($fields, $from, $to, $glossary, $style);
    }

    public function name(): string
    {
        return 'claude';
    }
}
