<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['ends_at' => 'datetime'];
    }

    public function scopeLive(Builder $query): Builder
    {
        return $query->where('status', 'live')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function isOpen(): bool
    {
        return $this->status === 'live' && (! $this->ends_at || $this->ends_at->isFuture());
    }

    public function totalVotes(): int
    {
        return (int) $this->options->sum('votes_count');
    }

    /** Whole-number percentage for an option; 0 when nobody has voted. */
    public function percent(PollOption $option): int
    {
        $total = $this->totalVotes();

        return $total ? (int) round($option->votes_count * 100 / $total) : 0;
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
