<?php
namespace App\Domain\Finance\Services;
use App\Domain\Finance\Models\{Subscription,Warranty,MaintenanceRecord};
use App\Domain\Obligations\Models\Task;
use App\Domain\Obligations\Services\ReminderService;
use Carbon\Carbon;
class LifeFinanceReminderService {
 public function __construct(private ReminderService $reminders){}
 private function task(int $household,int $user,string $title,?string $date,?int $docId=null):?Task {
  if(!$date)return null;
  $due=Carbon::parse($date)->setTime(9,0);
  if($due->isPast())return null;
  // Row-level duplicate check; caller persists reminder_task_id / cycle when possible.
  $t=Task::create(['household_id'=>$household,'user_id'=>$user,'document_id'=>$docId,'title'=>$title,'status'=>'pending','priority'=>'medium','due_at'=>$due,'labels'=>['life-finance']]);
  $this->reminders->recommend($t);return $t;
 }
 public function subscription(Subscription $s):void {
  if($s->status!=='active')return;
  $date=$s->cancellation_deadline && $s->cancellation_deadline->gte(today()) && $s->cancellation_deadline->lte($s->next_billing_date)?$s->cancellation_deadline:$s->next_billing_date;
  $dateString=$date->toDateString();
  if($s->last_generated_billing_date && $s->last_generated_billing_date->toDateString()===$dateString && $s->reminder_task_id)return;
  $this->cancelSubscriptionTask($s);
  $task=$this->task($s->household_id,$s->user_id,'Review subscription renewal: '.$s->name,$dateString,$s->document_id);
  if($task)$s->update(['last_generated_billing_date'=>$dateString,'reminder_task_id'=>$task->id]);
 }
 public function cancelSubscriptionTask(Subscription $s):void {
  if(!$s->reminder_task_id)return;
  $old=Task::where('household_id',$s->household_id)->find($s->reminder_task_id);
  if($old && !in_array($old->status,['completed','skipped'],true)){$old->update(['status'=>'skipped']);$this->reminders->cancel($old);}
  $s->update(['reminder_task_id'=>null]);
 }
 public function warranty(Warranty $w,int $userId):void {
  if($w->reminder_task_id || $w->status==='void')return;
  $task=$this->task($w->household_id,$userId,'Warranty expires: '.$w->asset->name,$w->end_date->toDateString(),$w->proof_document_id);
  if($task)$w->update(['reminder_task_id'=>$task->id]);
 }
 public function maintenance(MaintenanceRecord $m,int $userId):void {
  if($m->reminder_task_id || !$m->next_due_date)return;
  $task=$this->task($m->household_id,$userId,'Maintenance: '.$m->title,$m->next_due_date->toDateString(),$m->document_id);
  if($task)$m->update(['reminder_task_id'=>$task->id]);
 }
}
