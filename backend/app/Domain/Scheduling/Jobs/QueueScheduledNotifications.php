<?php
namespace App\Domain\Scheduling\Jobs;
use App\Domain\Scheduling\Models\{CalendarEvent,Notification};
use App\Domain\Scheduling\Services\NotificationEngine;
use App\Domain\Users\Models\User;
use App\Domain\Obligations\Models\Task;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\CarbonImmutable;
class QueueScheduledNotifications implements ShouldQueue {
 use Queueable;
 public function handle(NotificationEngine $engine):void {
  $from=now()->subDays(1);$until=now()->addDays(31);
  CalendarEvent::whereBetween('starts_at',[$from,$until])->where('status','confirmed')->chunkById(100,function($events)use($engine){
   foreach($events as $event){$user=User::find($event->user_id);if(!$user)continue;
    $preference=\App\Domain\Notifications\Models\NotificationPreference::where('user_id',$user->id)->first();if($preference && !$preference->reminder_enabled)continue;
    foreach($event->reminder_offsets??[] as $days){$at=CarbonImmutable::parse($event->starts_at)->subDays((int)$days);if($at->lt(now()->subDay()))continue;
     $engine->scheduleTemplate($user,$event->household_id,'calendar_reminder',['title'=>$event->title,'when'=>$event->starts_at->setTimezone($event->timezone)->format('Y-m-d H:i')],"event:$event->id:$days",$at,$event->task_id,$event->id);
    }
   }
  });
  // Important task escalation, deduplicated per task and day.
  Task::whereIn('priority',['high','urgent'])->whereIn('status',['pending','in_progress'])->where('due_at','<',now())->chunkById(100,function($tasks)use($engine){foreach($tasks as $task){$user=User::find($task->user_id);if(!$user)continue;
   $settings=\App\Domain\Scheduling\Models\NotificationSetting::where('user_id',$user->id)->first();if($settings && !$settings->escalation_enabled)continue;
   $engine->scheduleTemplate($user,$task->household_id,'task_escalation',['title'=>$task->title],"overdue:$task->id:".now()->toDateString(),CarbonImmutable::now(),$task->id,null,null,'high');
  }});
 }
}
