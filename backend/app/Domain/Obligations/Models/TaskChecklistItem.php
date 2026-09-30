<?php
namespace App\Domain\Obligations\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskChecklistItem extends Model {
 protected $fillable=['task_id','title','sort_order','is_completed','completed_at'];
 protected function casts():array{return ['is_completed'=>'boolean','completed_at'=>'datetime'];}
 public function task():BelongsTo{return $this->belongsTo(Task::class);}
}
