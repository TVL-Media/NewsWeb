<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedSource extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['trusted' => 'boolean', 'active' => 'boolean', 'last_fetched_at' => 'datetime'];
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FeedItem::class);
    }
}