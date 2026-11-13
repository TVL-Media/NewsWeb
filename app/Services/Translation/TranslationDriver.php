<?php

namespace App\Services\Translation;

interface TranslationDriver
{
    /**
     * Translate a set of named fields. Returns the same keys translated.
     *
     * @param  array<string,string>  $fields
     * @param  array<int,array{source_term:string,target_term:?string,do_not_translate:bool}>  $glossary
     * @return array<string,string>
     */
    public function translate(array $fields, string $from, string $to, array $glossary, string $style): array;

    public function name(): string;
}
