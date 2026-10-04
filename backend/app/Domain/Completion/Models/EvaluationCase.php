<?php

namespace App\Domain\Completion\Models;

use Illuminate\Database\Eloquent\Model;

class EvaluationCase extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'variant', 'metric_key', 'case_key', 'outcome',
        'expected', 'actual', 'latency_ms', 'evidence_refs', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'expected' => 'array',
            'actual' => 'array',
            'latency_ms' => 'integer',
            'evidence_refs' => 'array',
            'metadata' => 'array',
        ];
    }
}
