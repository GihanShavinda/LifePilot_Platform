<?php

namespace App\Domain\Actions\Models;

use App\Domain\Actions\Enums\{ActionRiskLevel, ActionStepStatus, ActionType};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ActionStep extends Model
{
    protected $fillable = [
        'action_plan_id',
        'sequence',
        'action_type',
        'risk_level',
        'status',
        'title',
        'description',
        'payload',
        'evidence_refs',
        'preview',
        'policy_snapshot',
        'idempotency_key',
        'requires_approval',
        'external_effect',
    ];

    protected function casts(): array
    {
        return [
            'action_type' => ActionType::class,
            'risk_level' => ActionRiskLevel::class,
            'status' => ActionStepStatus::class,
            'payload' => 'array',
            'evidence_refs' => 'array',
            'preview' => 'array',
            'policy_snapshot' => 'array',
            'requires_approval' => 'boolean',
            'external_effect' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActionPlan::class, 'action_plan_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ActionApproval::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ActionExecution::class);
    }
}
