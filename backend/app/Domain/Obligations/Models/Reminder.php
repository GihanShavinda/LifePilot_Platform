<?php
namespace App\Domain\Obligations\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Reminder extends Model {
 protected $fillable=['task_id','user_id','remind_at','snoozed_until','sent_at','channel','status','recommended'];
 protected function casts():array{return ['remind_at'=>'datetime','snoozed_until'=>'datetime','sent_at'=>'datetime','recommended'=>'boolean'];}
 public function task():BelongsTo{return $this->belongsTo(Task::class);}
}
