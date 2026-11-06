<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** Newsroom staff who sign in to Filament at /admin. Roles: editor, chief, admin. */
class Admin extends Authenticatable implements FilamentUser
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['editor', 'chief', 'admin'], true);
    }

    public function isChief(): bool
    {
        return in_array($this->role, ['chief', 'admin'], true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
