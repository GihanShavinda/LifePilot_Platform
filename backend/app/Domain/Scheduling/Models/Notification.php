<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

class Notification extends Model {  protected $table='life_notifications';protected $fillable=['user_id','household_id','calendar_event_id','task_id','reminder_id','type','title','body','payload','priority','status','dedupe_key','scheduled_at','read_at','delivered_at'];protected function casts():array{return ['payload'=>'array','scheduled_at'=>'datetime','read_at'=>'datetime','delivered_at'=>'datetime'];}public function deliveries():HasMany{return $this->hasMany(NotificationDelivery::class);} }
