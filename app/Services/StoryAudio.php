<?php

namespace App\Services;

use App\Contracts\TextToSpeech;
use App\Models\StoryVersion;

/** Generates the "Listen" audio for a story version: the headline, then the 60-word card. */
class StoryAudio
{
    public function __construct(private readonly TextToSpeech $tts) {}

    public function generate(StoryVersion $version): StoryVersion
    {
        $text = rtrim($version->headline, '.।').'. '.$version->card_text;

        $audio = $this->tts->synthesize($text, $version->language, "audio/stories/{$version->story_id}-{$version->language}");

        $version->update(['audio_path' => $audio['path'], 'audio_seconds' => $audio['seconds']]);

        return $version;
    }
}
