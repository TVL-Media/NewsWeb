<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Newsroom staff who sign in to the admin panel at /admin (admin guard).
 * Access comes from roles & permissions (Spatie). The old `role` column is kept for history only.
 */
class Admin extends Authenticatable
{
    use HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'admin';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'active'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'invite_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('Super Admin');
    }

    public function hasTwoFactor(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->join('');
    }

    public function notificationsFeed()
    {
        return $this->hasMany(AdminNotification::class)->latest('created_at');
    }

    public function roleNames(): string
    {
        return $this->roles->pluck('name')->join(', ');
    }
}
