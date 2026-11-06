<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PageStory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('expires_at', '>', now());
    }

    public function imageUrl(): string
    {
        return \Illuminate\Support\Facades\Storage::disk(config('filesystems.media'))->url($this->image_path);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function story(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}