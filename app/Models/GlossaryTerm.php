<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlossaryTerm extends Model
{
    protected $table = 'translation_glossary';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'do_not_translate' => 'boolean',
        ];
    }
}
