<?php

namespace App\Domain\Analytics\Models;

use Illuminate\Database\Eloquent\Model;

class PredictionRecord extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'prediction_type', 'method', 'model_version',
        'feature_version', 'input_fingerprint', 'prediction', 'confidence',
        'explanation', 'evidence_refs', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'prediction' => 'array',
            'confidence' => 'float',
            'evidence_refs' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
