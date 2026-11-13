<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_sensitive' => 'boolean', 'is_active' => 'boolean'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Icon or photo for the admin list (falls back to the photo). */
    public function visualUrl(): ?string
    {
        $p = $this->visual_type === 'icon' && $this->icon_path ? $this->icon_path : $this->image_path;

        return $p ? Storage::disk(config('filesystems.media'))->url($p) : null;
    }

    public function name(?string $lang = null): string
    {
        return ($lang ?? Lang::current()) === 'hi' ? $this->name_hi : $this->name_en;
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk(config('filesystems.media'))->url($this->image_path) : null;
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
