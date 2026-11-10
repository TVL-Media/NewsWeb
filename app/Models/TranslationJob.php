<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One AI translation of a story into the other language (EN ↔ HI). Approving it writes the story_versions row. */
class TranslationJob extends Model
{
    protected $guarded = ['id'];

    public const LANGS = ['en' => 'English', 'hi' => 'हिंदी'];

    protected function casts(): array
    {
        return ['result' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function requester()
    {
        return $this->belongsTo(Admin::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /** The source text changed after this translation was approved. */
    public function isOutOfDate(): bool
    {
        if ($this->status !== 'approved' || ! $this->reviewed_at) {
            return false;
        }
        $src = $this->story?->versions->firstWhere('language', $this->source_language);

        return $src && $src->updated_at && $src->updated_at->gt($this->reviewed_at);
    }
}
