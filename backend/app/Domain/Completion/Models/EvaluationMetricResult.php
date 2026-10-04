<?php

namespace App\Domain\Completion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationMetricResult extends Model
{
    protected $fillable = [
        'evaluation_run_id', 'variant', 'metric_key', 'label', 'value', 'unit',
        'status', 'sample_size', 'explanation', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'sample_size' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(EvaluationRun::class, 'evaluation_run_id');
    }
}
