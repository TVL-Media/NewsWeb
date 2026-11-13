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
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'phone', 'language', 'status', 'restricted_until', 'city_id', 'text_size', 'theme', 'notifications_per_day', 'onboarded_at', 'last_active_at',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'onboarded_at' => 'datetime',
            'last_active_at' => 'datetime',
            'restricted_until' => 'datetime',
        ];
    }

    protected $attributes = ['status' => 'active'];

    /** active | restricted | suspended. A restriction with an end date lapses by itself. */
    public function effectiveStatus(): string
    {
        if ($this->status === 'restricted' && $this->restricted_until && $this->restricted_until->isPast()) {
            return 'active';
        }

        return $this->status ?: 'active';
    }

    /** Readers who may like, comment, vote, report or create polls. */
    public function canInteract(): bool
    {
        return $this->effectiveStatus() === 'active';
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(UserStatusLog::class)->latest('created_at');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Share::class);
    }

    /** Mask a phone number for staff without users.view.pii. */
    public static function mask(?string $value): string
    {
        if (! $value) {
            return '—';
        }

        return mb_substr($value, 0, 2).'•••••'.mb_substr($value, -2);
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
