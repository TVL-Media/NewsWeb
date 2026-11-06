<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutoPublishSetting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sensitive_categories' => 'array',
            'sensitive_keywords' => 'array',
            'confidence_threshold' => 'float',
            'copy_threshold' => 'float',
        ];
    }

    /** The single settings row (created with defaults if missing). */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'sensitive_categories' => [],
            'sensitive_keywords' => [],
            'confidence_threshold' => 0.85,
            'copy_threshold' => 0.15,
        ]);
    }
}