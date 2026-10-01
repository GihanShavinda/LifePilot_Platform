<?php
namespace App\Domain\Scheduling\Jobs;
use App\Domain\Scheduling\Contracts\PushAdapter;
use App\Domain\Scheduling\Events\LifePilotUpdated;
use App\Domain\Scheduling\Models\{Notification,NotificationDelivery,NotificationSetting};
use App\Domain\Scheduling\Services\NotificationEngine;
use App\Domain\Notifications\Models\NotificationPreference;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;
class DeliverNotification implements ShouldQueue {
 use Queueable;public int $tries=3;public array $backoff=[60,300,900];
 public function __construct(public int $notificationId){}
 public function handle(NotificationEngine $engine,PushAdapter $push):void {
  $notice=Notification::with('deliveries')->find($this->notificationId);if(!$notice||$notice->status==='cancelled')return;
  if($notice->task_id){$task=\App\Domain\Obligations\Models\Task::find($notice->task_id);if(!$task||in_array($task->status,['completed','skipped'],true)){$notice->update(['status'=>'cancelled']);$notice->deliveries()->where('status','pending')->update(['status'=>'cancelled']);return;}}
  $user=\App\Domain\Users\Models\User::find($notice->user_id);if(!$user)return;
  $p=NotificationPreference::where('user_id',$user->id)->first();$settings=NotificationSetting::where('user_id',$user->id)->first();
  $retry=false;$latest=null;
  foreach($notice->deliveries as $delivery){
   if($delivery->status!=='pending'||($delivery->next_attempt_at && $delivery->next_attempt_at->isFuture()))continue;
   if($delivery->channel==='email'&&(!$p?->email_enabled||($notice->type==='reminder'&&!$p?->reminder_enabled))){$delivery->update(['status'=>'cancelled']);continue;}
   if($delivery->channel==='push'&&!$p?->push_enabled){$delivery->update(['status'=>'cancelled']);continue;}
   if($delivery->channel==='browser'&&!$settings?->browser_enabled){$delivery->update(['status'=>'cancelled']);continue;}
   $at=$engine->effectiveTime($notice,$delivery->channel);if($at->isFuture()){$delivery->update(['next_attempt_at'=>$at]);$latest=$latest===null||$at->lt($latest)?$at:$latest;continue;}
   try {
    if($delivery->channel==='email')Mail::raw($notice->body,fn($message)=>$message->to($user->email)->subject($notice->title));
    elseif($delivery->channel==='push')$delivery->provider_reference=$push->send($notice);
    elseif($delivery->channel==='in_app'){
     try{event(new LifePilotUpdated($user->id,'notification.created',['notification_id'=>$notice->id,'title'=>$notice->title]));}catch(Throwable $broadcastError){\Illuminate\Support\Facades\Log::warning('Websocket event unavailable',['error'=>$broadcastError->getMessage()]);}
    }
    elseif($delivery->channel==='browser'){
     try{event(new LifePilotUpdated($user->id,'reminder.created',['notification_id'=>$notice->id,'title'=>$notice->title]));}catch(Throwable $broadcastError){throw $broadcastError;}
     // Gateway dispatch cannot prove that a browser displayed the notification.
     $delivery->update(['status'=>'dispatched','attempts'=>$delivery->attempts+1,'delivered_at'=>null,'last_error'=>null]);continue;
    }
    $delivery->update(['status'=>'delivered','attempts'=>$delivery->attempts+1,'delivered_at'=>now(),'last_error'=>null,'provider_reference'=>$delivery->provider_reference]);
   }catch(Throwable $e){
    $attempts=$delivery->attempts+1;$max=config('lifepilot_scheduling.delivery_max_attempts',3);$failed=$attempts>=$max;
    $delivery->update(['status'=>$failed?'failed':'pending','attempts'=>$attempts,'last_error'=>mb_substr($e->getMessage(),0,2000),'next_attempt_at'=>$failed?null:now()->addSeconds([60,300,900][min($attempts-1,2)])]);
    if(!$failed){$retry=true;$at=\Carbon\CarbonImmutable::parse($delivery->next_attempt_at);$latest=$latest===null||$at->lt($latest)?$at:$latest;}
   }
  }
  $notice->update(['status'=>$notice->deliveries()->where('status','delivered')->exists()?'delivered':($notice->deliveries()->where('status','pending')->exists()?'pending':($notice->deliveries()->where('status','dispatched')->exists()?'dispatched':'failed')),'delivered_at'=>$notice->delivered_at?:($notice->deliveries()->where('status','delivered')->exists()?now():null)]);
  if($latest)self::dispatch($notice->id)->delay($latest);
 }
}
