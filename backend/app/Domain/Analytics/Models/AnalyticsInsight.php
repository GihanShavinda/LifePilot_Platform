<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsInsight extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'insight_key', 'type', 'severity', 'title',
        'message', 'metrics', 'evidence_refs', 'model_version', 'feature_version',
        'generated_at',
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
