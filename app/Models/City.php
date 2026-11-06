<?php

namespace App\Models;

use App\Support\Lang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class City extends Model
{
    protected $guarded = [];

    public function name(?string $lang = null): string
    {
        return ($lang ?? Lang::current()) === 'hi' ? $this->name_hi : $this->name_en;
    }

    public function page(): HasOne
    {
        return $this->hasOne(Page::class)->where('type', 'city');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
