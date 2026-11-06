<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoryCluster extends Model
{
    protected $guarded = [];

    public function story(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FeedItem::class, 'cluster_id');
    }
}