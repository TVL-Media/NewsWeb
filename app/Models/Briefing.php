<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Briefing extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date', 'story_ids' => 'array', 'chapter_offsets' => 'array'];
    }

    /** The 5 stories in briefing order. */
    public function stories(): \Illuminate\Support\Collection
    {
        $stories = Story::with('versions', 'category')->whereIn('id', $this->story_ids ?? [])->get()->keyBy('id');

        return collect($this->story_ids)->map(fn ($id) => $stories->get($id))->filter()->values();
    }

    /** Start second of each story in the joined audio for a language: {"en": [0, 31, …], "hi": […]} */
    public function offsets(string $lang): array
    {
        $offsets = $this->chapter_offsets ?? [];

        return $offsets[$lang] ?? $offsets['en'] ?? (array_is_list($offsets) ? $offsets : []);
    }

    public function audioUrl(string $lang): ?string
    {
        $path = $this->{"audio_path_{$lang}"} ?: $this->audio_path_en ?: $this->audio_path_hi;

        return $path ? \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($path) : null;
    }

    public static function today(): ?self
    {
        return static::query()->whereDate('date', now(config('app.timezone'))->toDateString())->first();
    }
}