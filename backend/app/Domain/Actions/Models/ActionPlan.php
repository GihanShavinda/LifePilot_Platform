<?php

namespace App\Domain\Actions\Models;

use App\Domain\Actions\Enums\{ActionPlanStatus, ActionRiskLevel};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class ActionPlan extends Model
{
    protected $fillable = [
        'household_id',
        'user_id',
        'source_conversation_message_id',
        'request',
        'origin',
        'status',
        'overall_risk',
        'idempotency_key',
        'evidence_refs',
        'metadata',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ActionPlanStatus::class,
            'overall_risk' => ActionRiskLevel::class,
            'evidence_refs' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ActionStep::class)->orderBy('sequence');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ActionApproval::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ActionExecution::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Users\Models\User::class);
    }
}
