<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $guarded = [];

    public const TARGETS = ['story' => Story::class, 'comment' => Comment::class, 'poll' => Poll::class, 'reel' => Reel::class];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function handler(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }

    /** The reader who wrote the reported comment or poll (stories are written by the newsroom). */
    public function targetAuthorId(): ?int
    {
        $t = $this->target();

        return match (true) {
            $t instanceof Comment => $t->user_id,
            $t instanceof Poll => $t->created_by,
            default => null,
        };
    }

    /** Short text for admin lists. */
    public function targetLabel(): string
    {
        $t = $this->target();

        return match (true) {
            $t instanceof Story => $t->title(),
            $t instanceof Comment => '“'.\Illuminate\Support\Str::limit($t->body, 90).'”',
            $t instanceof Poll => $t->question,
            default => ucfirst($this->target_type).' #'.$this->target_id.' (deleted)',
        };
    }

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