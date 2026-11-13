<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Reel extends Model
{
    public const STATUSES = ['processing', 'pending_approval', 'published', 'rejected', 'failed'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (Reel $reel) {
            $paths = array_filter([$reel->video_720_path, $reel->video_480_path, $reel->thumbnail_path, $reel->source_path]);
            if ($paths) {
                Storage::disk(config('filesystems.media'))->delete(array_values($paths));
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function markFailed(string $message): void
    {
        $this->update(['status' => 'failed', 'error_message' => mb_strimwidth($message, 0, 2000, '…')]);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** Published and, for a story reel, its story is still live (an unpublished story takes its reels with it). */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->published()
            ->where(fn (Builder $q) => $q->whereNull('story_id')->orWhereHas('story', fn (Builder $s) => $s->published()));
    }

    public function isVisible(): bool
    {
        return self::visible()->whereKey($this->id)->exists();
    }

    public function videoUrl(string $size = '720'): ?string
    {
        $path = $size === '480' ? $this->video_480_path : $this->video_720_path;

        return $path ? Storage::disk(config('filesystems.media'))->url($path) : null;
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk(config('filesystems.media'))->url($this->thumbnail_path) : null;
    }

    /** The published reel of the same story in the other language, if there is one. */
    public function otherLanguage(): ?self
    {
        if (! $this->story_id) {
            return null;
        }

        return self::visible()
            ->where('story_id', $this->story_id)
            ->where('language', $this->language === 'hi' ? 'en' : 'hi')
            ->first();
    }

    /** Where this reel's files live on the media disk. */
    public function mediaDir(): string
    {
        return "reels/{$this->id}";
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function views(): HasMany
    {
        return $this->hasMany(ReelView::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(Save::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }
}
