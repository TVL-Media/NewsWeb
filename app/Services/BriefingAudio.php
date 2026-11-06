<?php

namespace App\Services;

use App\Models\Briefing;
use App\Models\StoryVersion;
use Illuminate\Support\Facades\Storage;

/**
 * Joins the 5 stories' audio into one Morning 5 track per language and records where each
 * story starts (chapter_offsets), so the player can move the "In this briefing" highlight.
 * WAV (FakeTts): PCM data is merged under one header. MP3 (Google TTS): frames are concatenated.
 */
class BriefingAudio
{
    public function __construct(private readonly StoryAudio $storyAudio) {}

    public function build(Briefing $briefing): Briefing
    {
        $stories = $briefing->stories();
        $disk = Storage::disk(config('filesystems.media'));
        $offsets = [];
        $total = 0;

        foreach (['en', 'hi'] as $lang) {
            $parts = [];
            $langOffsets = [];
            $seconds = 0;

            foreach ($stories as $story) {
                $version = $story->version($lang);
                if (! $version) {
                    continue;
                }
                if (! $version->audio_path || ! $disk->exists($version->audio_path)) {
                    $version = $this->storyAudio->generate($version);
                }
                $langOffsets[] = $seconds;
                $seconds += (int) $version->audio_seconds;
                $parts[] = $version;
            }

            if (! $parts) {
                continue;
            }

            $ext = pathinfo($parts[0]->audio_path, PATHINFO_EXTENSION);
            $path = "audio/briefings/{$briefing->date->toDateString()}-{$lang}.{$ext}";
            $disk->put($path, $ext === 'wav' ? $this->joinWav($parts) : $this->joinRaw($parts));

            $briefing->{"audio_path_{$lang}"} = $path;
            if ($lang === 'en' || ! $offsets) {
                $offsets = $langOffsets;
                $total = $seconds;
            }
            $offsetsByLang[$lang] = $langOffsets;
        }

        // Offsets are stored per language: {"en": [...], "hi": [...]}
        $briefing->chapter_offsets = $offsetsByLang ?? [];
        $briefing->audio_seconds = $total;
        $briefing->save();

        return $briefing;
    }

    /** @param StoryVersion[] $parts */
    private function joinWav(array $parts): string
    {
        $disk = Storage::disk(config('filesystems.media'));
        $header = null;
        $data = '';

        foreach ($parts as $part) {
            $wav = $disk->get($part->audio_path);
            $pos = strpos($wav, 'data');
            $header ??= substr($wav, 0, $pos);
            $data .= substr($wav, $pos + 8, unpack('V', substr($wav, $pos + 4, 4))[1]);
        }

        // Rewrite the RIFF and data sizes for the merged file.
        $header = substr_replace($header, pack('V', strlen($header) - 8 + 8 + strlen($data)), 4, 4);

        return $header.'data'.pack('V', strlen($data)).$data;
    }

    /** @param StoryVersion[] $parts */
    private function joinRaw(array $parts): string
    {
        $disk = Storage::disk(config('filesystems.media'));

        return implode('', array_map(fn ($p) => $disk->get($p->audio_path), $parts));
    }
}
