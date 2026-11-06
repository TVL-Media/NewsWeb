<?php

namespace App\Contracts;

interface TextToSpeech
{
    /**
     * Turn text into speech and store it on the media disk at $path (without extension).
     *
     * @param  string  $language  en|hi
     * @return array{path: string, seconds: int} stored file path (with extension) and duration
     */
    public function synthesize(string $text, string $language, string $path): array;
}
