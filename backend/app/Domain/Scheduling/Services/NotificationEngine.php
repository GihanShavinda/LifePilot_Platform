<?php
namespace App\Domain\Scheduling\Services;
use App\Domain\Scheduling\Models\{Notification,NotificationDelivery,NotificationSetting,NotificationTemplate};
use App\Domain\Scheduling\Jobs\DeliverNotification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
class NotificationEngine {
 public function schedule(User $user,int $household,string $type,string $title,string $body,string $dedupe,CarbonImmutable $at,?int $task=null,?int $event=null,?int $reminder=null,string $priority='normal'):Notification {
  $membership=$user->householdMemberships()->where('household_id',$household)->exists();if(!$membership)throw new \Illuminate\Auth\Access\AuthorizationException();
  $record=DB::transaction(function()use($user,$household,$type,$title,$body,$dedupe,$at,$task,$event,$reminder,$priority){
   $record=Notification::firstOrCreate(['user_id'=>$user->id,'dedupe_key'=>hash('sha256',$dedupe)],['household_id'=>$household,'type'=>$type,'title'=>$title,'body'=>$body,'task_id'=>$task,'calendar_event_id'=>$event,'reminder_id'=>$reminder,'priority'=>$priority,'scheduled_at'=>$at,'status'=>'pending']);
   $p=NotificationPreference::where('user_id',$user->id)->first();$s=NotificationSetting::firstOrCreate(['user_id'=>$user->id],['timezone'=>$user->timezone?:'UTC']);
   // In-app notifications remain available for review; other channels are opt-in.
   $channels=[];if($s->in_app_enabled)$channels[]='in_app';
   if($p?->email_enabled && ($type!=='reminder'||$p?->reminder_enabled))$channels[]='email';
   if($p?->push_enabled && config('lifepilot_scheduling.push.provider')!=='disabled' && \App\Domain\Scheduling\Models\BrowserPushSubscription::where('user_id',$user->id)->exists() && ($type!=='reminder'||$p?->reminder_enabled))$channels[]='push';
   if($s->browser_enabled)$channels[]='browser';
   foreach($channels as $channel)$record->deliveries()->firstOrCreate(['channel'=>$channel],['status'=>'pending','next_attempt_at'=>$at]);
   return $record;
  });
  if($record->wasRecentlyCreated) DeliverNotification::dispatch($record->id)->delay($record->scheduled_at->isFuture()?$record->scheduled_at:now());
  return $record;
 }
 public function scheduleTemplate(User $user,int $household,string $type,array $variables,string $dedupe,CarbonImmutable $at,?int $task=null,?int $event=null,?int $reminder=null,string $priority='normal'):Notification {
  $template=NotificationTemplate::where('key',$type)->where('enabled',true)->first();
  if(!$template)throw new \RuntimeException('Notification template not configured: '.$type);
  $tokens=[];foreach($variables as $key=>$value){if(is_scalar($value))$tokens['{'.$key.'}']=mb_substr((string)$value,0,255);}
  return $this->schedule($user,$household,$type,strtr($template->title_template,$tokens),strtr($template->body_template,$tokens),$dedupe,$at,$task,$event,$reminder,$priority);
 }
 public function effectiveTime(Notification $notice,string $channel):CarbonImmutable {
  $target=CarbonImmutable::parse($notice->scheduled_at);
  if($channel==='in_app')return $target;
  $settings=NotificationSetting::where('user_id',$notice->user_id)->first();
  if(!$settings||!$settings->quiet_start||!$settings->quiet_end)return $target;
  $tz=$settings->timezone?:'UTC';$local=$target->setTimezone($tz);$start=CarbonImmutable::parse($local->toDateString().' '.$settings->quiet_start,$tz);$end=CarbonImmutable::parse($local->toDateString().' '.$settings->quiet_end,$tz);
  if($end->lte($start)){if($local->lt($start))$start=$start->subDay();else $end=$end->addDay();}
  return $local->gte($start)&&$local->lt($end)?$end->utc():$target;
 }
}
