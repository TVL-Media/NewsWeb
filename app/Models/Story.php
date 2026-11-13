<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_breaking' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'submitted_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Published stories, plus corrected ones (they stay public with a correction label). */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['published', 'corrected'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * The version for a language, falling back to the other language when it is missing.
     * Works on eager-loaded `versions` to avoid extra queries.
     */
    public function version(?string $lang = null): ?StoryVersion
    {
        $lang ??= Lang::current();
        $versions = $this->relationLoaded('versions') ? $this->versions : $this->versions()->get();

        return $versions->firstWhere('language', $lang)
            ?? $versions->firstWhere('language', $lang === 'hi' ? 'en' : 'hi');
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk(config('filesystems.media'))->url($this->photo_path) : null;
    }

    public function url(): string
    {
        return route('story.show', $this);
    }

    /** "25 min ago", "3 hr ago", "2 days ago" — in the reader's language. */
    public function ago(?string $lang = null): string
    {
        return Lang::ago($this->published_at, $lang);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(StoryVersion::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function aiCheck(): HasOne
    {
        return $this->hasOne(AiCheck::class)->latestOfMany();
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    public function cluster(): HasOne
    {
        return $this->hasOne(StoryCluster::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
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

    public function readingEvents(): HasMany
    {
        return $this->hasMany(ReadingEvent::class);
    }

    public function polls(): HasMany
    {
        return $this->hasMany(Poll::class);
    }

    public function reels(): HasMany
    {
        return $this->hasMany(Reel::class);
    }

    // ── Admin panel ───────────────────────────────────────────────────────

    public const STATUSES = [
        'draft' => 'Draft', 'pending_approval' => 'In review', 'changes_requested' => 'Changes requested',
        'scheduled' => 'Scheduled', 'published' => 'Published', 'corrected' => 'Corrected',
        'rejected' => 'Rejected', 'removed' => 'Unpublished',
    ];

    public function isLive(): bool
    {
        return in_array($this->status, ['published', 'corrected'], true);
    }

    /** English headline (falls back to Hindi) for admin lists. */
    public function title(): string
    {
        return $this->version('en')?->headline ?? '(untitled)';
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_reviewer_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(StorySnapshot::class)->latest('version_no');
    }

    public function publishLogs(): HasMany
    {
        return $this->hasMany(PublishLog::class)->latest('attempted_at');
    }

    public function translationJobs(): HasMany
    {
        return $this->hasMany(TranslationJob::class)->latest();
    }
}
