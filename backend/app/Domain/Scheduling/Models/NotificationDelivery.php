<?php
namespace App\Domain\Scheduling\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};

class NotificationDelivery extends Model {  protected $fillable=['notification_id','channel','status','attempts','next_attempt_at','delivered_at','last_error','provider_reference'];protected function casts():array{return ['next_attempt_at'=>'datetime','delivered_at'=>'datetime'];}public function notification():BelongsTo{return $this->belongsTo(Notification::class);} }
