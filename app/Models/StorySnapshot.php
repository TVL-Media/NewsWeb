<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A saved copy of a story (both languages + details) for version history and restore. */
class StorySnapshot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    public function author()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
