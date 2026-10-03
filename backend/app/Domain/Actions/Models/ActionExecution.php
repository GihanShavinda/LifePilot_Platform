<?php

namespace App\Domain\Actions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasOne};

class ActionExecution extends Model
{
    protected $fillable = [
        'action_plan_id',
        'action_step_id',
        'user_id',
        'status',
        'attempt',
        'idempotency_key',
        'started_at',
        'finished_at',
        'error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function step(): BelongsTo
    {
        return $this->belongsTo(ActionStep::class, 'action_step_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(ActionResult::class);
    }
}
