<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Collaboration\Traits\HasHouseholdSharing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CalendarEvent extends Model
{
    use SoftDeletes, HasHouseholdSharing;

    protected $fillable = [
        'household_id', 'user_id', 'task_id', 'document_id', 'calendar_connection_id', 'external_event_id',
        'title', 'description', 'location', 'starts_at', 'ends_at', 'timezone', 'status', 'reminder_offsets',
        'recurrence_frequency', 'recurrence_interval', 'recurrence_until', 'series_key', 'occurrence_at',
    ];

    public static function sharingResourceType(): string { return 'calendar_event'; }

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'reminder_offsets' => 'array',
            'recurrence_until' => 'datetime', 'occurrence_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo { return $this->belongsTo(\App\Domain\Obligations\Models\Task::class); }
    public function document(): BelongsTo { return $this->belongsTo(\App\Domain\Documents\Models\Document::class); }
}
