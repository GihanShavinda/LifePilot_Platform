<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class DataQualityCheck extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'check_key', 'status', 'title', 'message',
        'metrics', 'evidence_refs', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'evidence_refs' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
