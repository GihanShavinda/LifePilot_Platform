<?php
namespace App\Domain\Obligations\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class TaskActivity extends Model {
 protected $fillable=['task_id','user_id','event','details'];
 protected function casts():array{return ['details'=>'array'];}
 public function task():BelongsTo{return $this->belongsTo(Task::class);}
 public function user():BelongsTo{return $this->belongsTo(\App\Domain\Users\Models\User::class);}
}
