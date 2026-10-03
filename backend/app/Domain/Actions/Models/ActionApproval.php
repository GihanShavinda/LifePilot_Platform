<?php

namespace App\Domain\Actions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionApproval extends Model
{
    protected $fillable = [
        'action_plan_id',
        'action_step_id',
        'user_id',
        'decision',
        'explicit_high_risk_ack',
        'comment',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'explicit_high_risk_ack' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActionPlan::class, 'action_plan_id');
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ActionStep::class, 'action_step_id');
    }
}
