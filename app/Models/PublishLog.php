<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PublishLog extends Model
{
    protected $table = 'publish_logs';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'attempted_at' => 'datetime',
        ];
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}
