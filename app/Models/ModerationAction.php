<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationAction extends Model
{
    protected $table = 'moderation_actions';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

}
