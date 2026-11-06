<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** One language (en|hi) of a story: headline, 60-word card, key points, explainers, audio. */
class StoryVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'ai_assisted' => 'boolean',
        ];
    }

    public function audioUrl(): ?string
    {
        return $this->audio_path ? Storage::disk(config('filesystems.media'))->url($this->audio_path) : null;
    }

    /** "0:40" */
    public function audioLength(): string
    {
        $s = (int) $this->audio_seconds;

        return intdiv($s, 60).':'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
    }

    /** Full text split into paragraphs. */
    public function paragraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split("/\n\s*\n/", (string) $this->full_text))));
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
