<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminLoginLog extends Model
{
    protected $table = 'admin_login_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }
}
