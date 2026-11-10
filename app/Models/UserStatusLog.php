<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserStatusLog extends Model
{
    protected $table = 'user_status_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'until' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
