<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/** A followable page: a topic (Cricket), a city (Lucknow) or a show (Good News Daily). */
class Page extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verified' => 'boolean',
            'upcoming' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function name(?string $lang = null): string
    {
        return ($lang ?? Lang::current()) === 'hi' && $this->name_hi ? $this->name_hi : $this->name;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk(config('filesystems.media'))->url($this->avatar_path) : null;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk(config('filesystems.media'))->url($this->cover_path) : null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function pinnedStory(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'pinned_story_id');
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'page_follows')->withTimestamps();
    }

    public function pageStories(): HasMany
    {
        return $this->hasMany(PageStory::class);
    }
}
