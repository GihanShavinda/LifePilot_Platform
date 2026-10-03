<?php

namespace App\Domain\Actions\Models;

use App\Domain\Actions\Enums\ActionRiskLevel;
use Illuminate\Database\Eloquent\Model;

class ActionPolicy extends Model
{
    protected $fillable = [
        'action_type',
        'risk_level',
        'requires_approval',
        'requires_explicit_high_risk_ack',
        'enabled',
        'conditions',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'risk_level' => ActionRiskLevel::class,
            'requires_approval' => 'boolean',
            'requires_explicit_high_risk_ack' => 'boolean',
            'enabled' => 'boolean',
            'conditions' => 'array',
        ];
    }
}
