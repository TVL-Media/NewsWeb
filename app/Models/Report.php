<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = [];

    public const TARGETS = ['story' => Story::class, 'comment' => Comment::class, 'poll' => Poll::class, 'reel' => Reel::class];

    public function reporter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function target(): ?\Illuminate\Database\Eloquent\Model
    {
        $class = self::TARGETS[$this->target_type] ?? null;

        return $class ? $class::find($this->target_id) : null;
    }
}