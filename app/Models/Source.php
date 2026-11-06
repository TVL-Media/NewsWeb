<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Source extends Model
{
    protected $guarded = [];

    public function stories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Story::class);
    }
}