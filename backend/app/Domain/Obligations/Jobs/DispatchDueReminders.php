<?php
namespace App\Domain\Obligations\Jobs;
use App\Domain\Obligations\Models\Reminder;
use App\Domain\Obligations\Services\TaskActivityService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class DispatchDueReminders implements ShouldQueue {
 use Queueable;
 public function handle():void {
  Reminder::where('status','scheduled')->where('remind_at','<=',now())->where(fn($q)=>$q->whereNull('snoozed_until')->orWhere('snoozed_until','<=',now()))
   ->with('task')->chunkById(100,function($reminders){
    foreach($reminders as $reminder){
     $task=$reminder->task;
     if(!$task || $task->trashed() || in_array($task->status,['completed','skipped'],true)){$reminder->update(['status'=>'cancelled']);continue;}
     $reminder->update(['status'=>'sent','sent_at'=>now()]);
     TaskActivityService::record($task,null,'reminder_sent',['reminder_id'=>$reminder->id]);
    }
   });
 }
}
