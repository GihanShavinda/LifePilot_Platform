<?php
namespace App\Domain\Obligations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,BelongsToMany,HasMany};

class Task extends Model
{
    use SoftDeletes;
    protected $fillable = ['household_id','user_id','obligation_id','document_id','recurring_rule_id','parent_task_id','title','description','priority','status','labels','due_at','completed_at','completion_evidence','occurrence_number'];
    protected function casts(): array { return ['labels'=>'array','due_at'=>'datetime','completed_at'=>'datetime']; }
    public function obligation(): BelongsTo { return $this->belongsTo(Obligation::class); }
    public function document(): BelongsTo { return $this->belongsTo(\App\Domain\Documents\Models\Document::class); }
    public function recurrence(): BelongsTo { return $this->belongsTo(RecurringRule::class, 'recurring_rule_id'); }
    public function checklist(): HasMany { return $this->hasMany(TaskChecklistItem::class)->orderBy('sort_order'); }
    public function reminders(): HasMany { return $this->hasMany(Reminder::class); }
    public function activities(): HasMany { return $this->hasMany(TaskActivity::class)->latest(); }
    public function dependencies(): BelongsToMany { return $this->belongsToMany(self::class, 'task_dependencies', 'task_id', 'depends_on_task_id')->withTimestamps(); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_task_id'); }
    public function effectiveStatus(): string {
        if ($this->status === 'pending' || $this->status === 'in_progress') {
            if ($this->due_at && $this->due_at->isPast()) return 'overdue';
        }
        return $this->status;
    }
}
