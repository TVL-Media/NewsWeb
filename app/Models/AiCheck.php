<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sensitive_reasons' => 'array',
            'risk_flags' => 'array',
            'reasons' => 'array',
            'fact_mismatches' => 'array',
            'confidence' => 'float',
            'copy_score' => 'float',
        ];
    }

    public function story(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}