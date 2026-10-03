<?php

namespace App\Domain\Actions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionResult extends Model
{
    protected $fillable = [
        'action_execution_id',
        'action_step_id',
        'status',
        'entity_type',
        'entity_id',
        'result',
        'verification',
        'rollback_status',
        'rollback_result',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'verification' => 'array',
            'rollback_result' => 'array',
        ];
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ActionExecution::class, 'action_execution_id');
    }
}
