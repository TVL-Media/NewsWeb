<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'language', 'city_id', 'text_size', 'theme', 'notifications_per_day', 'onboarded_at', 'last_active_at',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'onboarded_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    /** First letter for the red rounded-square avatar. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name ?: 'N', 0, 1));
    }

    public function firstName(): string
    {
        return $this->name ? explode(' ', trim($this->name))[0] : 'there';
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function followedPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_follows')->withTimestamps();
    }

    public function savedStories(): BelongsToMany
    {
        return $this->belongsToMany(Story::class, 'saves')->withTimestamps();
    }

    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function readingEvents(): HasMany
    {
        return $this->hasMany(ReadingEvent::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function streak(): HasOne
    {
        return $this->hasOne(Streak::class);
    }
}
