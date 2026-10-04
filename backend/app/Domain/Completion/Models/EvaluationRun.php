<?php

namespace App\Domain\Completion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationRun extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'label', 'status', 'summary', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(EvaluationMetricResult::class);
    }
}
