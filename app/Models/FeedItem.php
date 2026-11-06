<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedItem extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'processed' => 'boolean'];
    }

    public function feedSource(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(FeedSource::class);
    }

    public function cluster(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(StoryCluster::class, 'cluster_id');
    }
}