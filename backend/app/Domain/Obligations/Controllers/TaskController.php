<?php
namespace App\Domain\Obligations\Controllers;

use App\Domain\Documents\Models\Document;
use App\Domain\Obligations\Models\{Task,RecurringRule,Reminder};
use App\Domain\Obligations\Services\{ReminderService,RecurrenceService,TaskActivityService,TaskDependencyService};
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse,Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class TaskController {
 private function household(Request $request,bool $write=false):int {
  $member=$request->user()->householdMemberships()->first();
  if(!$member)throw new AuthorizationException('Household required.');
  $role=$member->role instanceof \BackedEnum?$member->role->value:(string)$member->role;
  if($write && !in_array($role,['owner','admin','member','family_member'],true))throw new AuthorizationException('Read-only household role.');
  return $member->household_id;
 }
 private function task(Request $request,int $id,bool $write=false):Task {
  $h=$this->household($request,$write);
  
  $task=Task::accessibleTo($request->user())->where('household_id',$h)->findOrFail($id);
  if($write)app(\App\Domain\Collaboration\Services\SharedResourceService::class)->ensureWrite($request->user(),$task);
  return $task;
 }
 private function rules(bool $create=true):array {
  $required=$create?'required':'sometimes';
  return [
   'title'=>[$required,'string','max:255'],'description'=>'nullable|string|max:5000',
   'priority'=>['sometimes',Rule::in(['low','medium','high','urgent'])],
   'labels'=>'sometimes|array|max:20','labels.*'=>'string|max:40',
   'due_at'=>'nullable|date','document_id'=>'nullable|integer',
   'parent_task_id'=>'nullable|integer',
  ];
 }
 private function formatted(Task $task):array {
  return array_merge($task->load(['checklist','dependencies:id,title,status','reminders','activities.user:id,name','obligation:id,type,amount,currency'])->toArray(),['effective_status'=>$task->effectiveStatus()]);
 }
 public function index(Request $request):JsonResponse {
  $h=$this->household($request);
  $q=Task::accessibleTo($request->user())->where('household_id',$h)->with(['checklist','reminders','obligation:id,type,amount,currency']);
  $view=$request->query('view','all');$now=now();
  if($view==='today')$q->whereDate('due_at',$now->toDateString())->whereNotIn('status',['completed','skipped']);
  if($view==='upcoming')$q->where('due_at','>',$now)->whereNotIn('status',['completed','skipped']);
  if($view==='overdue')$q->where('due_at','<',$now)->whereNotIn('status',['completed','skipped']);
  if($request->filled('status'))$q->where('status',$request->query('status'));
  if($request->filled('document_id'))$q->where('document_id',$request->integer('document_id'));
  if($request->filled('from'))$q->where('due_at','>=',$request->query('from'));
  if($request->filled('to'))$q->where('due_at','<=',$request->query('to'));
  $page=$q->orderByRaw('case when due_at is null then 1 else 0 end')->orderBy('due_at')->paginate(50);
  $page->getCollection()->transform(function(Task $task){$task->setAttribute('effective_status',$task->effectiveStatus());return $task;});
  return ApiResponse::success(['tasks'=>$page]);
 }
 public function show(Request $request,int $id):JsonResponse {return ApiResponse::success(['task'=>$this->formatted($this->task($request,$id))]);}
 public function store(Request $request,ReminderService $reminders,RecurrenceService $recurrence):JsonResponse {
  $h=$this->household($request,true);
  $data=$request->validate(array_merge($this->rules(),[
   'recurrence'=>'nullable|array','recurrence.frequency'=>['required_with:recurrence',Rule::in(['daily','weekly','monthly','yearly'])],
   'recurrence.interval'=>'sometimes|integer|min:1|max:365','recurrence.until_at'=>'nullable|date|after:now',
   'recurrence.max_occurrences'=>'nullable|integer|min:1|max:1000',
  ]));
  if(!empty($data['document_id']))Document::accessibleTo($request->user())->where('household_id',$h)->findOrFail($data['document_id']);
  if(!empty($data['parent_task_id']))Task::accessibleTo($request->user())->where('household_id',$h)->findOrFail($data['parent_task_id']);
  if(!empty($data['recurrence']) && empty($data['due_at']))throw ValidationException::withMessages(['due_at'=>'Recurring tasks require a due date.']);
  if(!empty($data['due_at']) && now()->gt(\Carbon\Carbon::parse($data['due_at'])))throw ValidationException::withMessages(['due_at'=>'New tasks cannot start with an impossible or past due date.']);
  return DB::transaction(function()use($data,$h,$request,$reminders,$recurrence){
   $recurring=$data['recurrence']??null;unset($data['recurrence']);
   if($recurring){
    $start=\Carbon\CarbonImmutable::parse($data['due_at']);
    $rule=RecurringRule::create(['household_id'=>$h,'frequency'=>$recurring['frequency'],'interval'=>$recurring['interval']??1,
      'starts_at'=>$start,'until_at'=>$recurring['until_at']??null,'max_occurrences'=>$recurring['max_occurrences']??null,
      'generated_occurrences'=>1,'next_at'=>$recurrence->next($start,$recurring['frequency'],$recurring['interval']??1)]);
    $data['recurring_rule_id']=$rule->id;$data['occurrence_number']=1;
   }
   $task=Task::create(array_merge($data,['user_id'=>$request->user()->id,'household_id'=>$h,'status'=>'pending']));
   TaskActivityService::record($task,$request->user()->id,'created');
   event(new \App\Domain\Scheduling\Events\LifePilotUpdated($request->user()->id,'task.updated',['task_id'=>$task->id]));
   $reminders->recommend($task);
   return ApiResponse::success(['task'=>$this->formatted($task)],201);
  });
 }
 public function update(Request $request,int $id,ReminderService $reminders,TaskDependencyService $dependencies):JsonResponse {
  $task=$this->task($request,$id,true);
  $data=$request->validate(array_merge($this->rules(false),['status'=>['sometimes',Rule::in(['pending','in_progress','completed','skipped'])], 'completion_evidence'=>'nullable|string|max:5000']));
  if(isset($data['document_id']))Document::accessibleTo($request->user())->where('household_id',$task->household_id)->findOrFail($data['document_id']);
  if(isset($data['parent_task_id']))Task::accessibleTo($request->user())->where('household_id',$task->household_id)->where('id','!=',$task->id)->findOrFail($data['parent_task_id']);
  if(isset($data['status']) && $data['status']==='completed')$dependencies->ensureReady($task);
  if(isset($data['due_at']) && $data['due_at'] && \Carbon\Carbon::parse($data['due_at'])->isPast() && ($data['status']??$task->status)!=='completed')throw ValidationException::withMessages(['due_at'=>'Choose a valid future due date.']);
  if(isset($data['status']) && $data['status']==='completed')$data['completed_at']=now();
  if(isset($data['status']) && ($data['status']??$task->status)!=='completed')$data['completed_at']=null;
  $before=$task->only(['status','due_at','title']);$task->update($data);
  if(in_array($task->status,['completed','skipped'],true))$reminders->cancel($task);
  elseif(array_key_exists('due_at',$data)){$reminders->cancel($task);$reminders->recommend($task);}
  TaskActivityService::record($task,$request->user()->id,'updated',['before'=>$before,'changes'=>array_keys($data)]);
  event(new \App\Domain\Scheduling\Events\LifePilotUpdated($request->user()->id,'task.updated',['task_id'=>$task->id]));
  return ApiResponse::success(['task'=>$this->formatted($task)]);
 }
 public function destroy(Request $request,int $id,ReminderService $reminders):JsonResponse {
  $task=$this->task($request,$id,true);$reminders->cancel($task);TaskActivityService::record($task,$request->user()->id,'deleted');$task->delete();return ApiResponse::success(['message'=>'Task deleted.']);
 }
 public function addChecklist(Request $request,int $id):JsonResponse {
  $task=$this->task($request,$id,true);$data=$request->validate(['title'=>'required|string|max:255']);
  $item=$task->checklist()->create(['title'=>$data['title'],'sort_order'=>($task->checklist()->max('sort_order')??0)+1]);
  TaskActivityService::record($task,$request->user()->id,'checklist_added',['item_id'=>$item->id]);
  return ApiResponse::success(['item'=>$item],201);
 }
 public function updateChecklist(Request $request,int $id,int $itemId):JsonResponse {
  $task=$this->task($request,$id,true);$item=$task->checklist()->findOrFail($itemId);
  $data=$request->validate(['title'=>'sometimes|string|max:255','is_completed'=>'sometimes|boolean']);
  if(array_key_exists('is_completed',$data))$data['completed_at']=$data['is_completed']?now():null;
  $item->update($data);TaskActivityService::record($task,$request->user()->id,'checklist_updated',['item_id'=>$item->id]);return ApiResponse::success(['item'=>$item]);
 }
 public function deleteChecklist(Request $request,int $id,int $itemId):JsonResponse {
  $task=$this->task($request,$id,true);$task->checklist()->findOrFail($itemId)->delete();TaskActivityService::record($task,$request->user()->id,'checklist_deleted',['item_id'=>$itemId]);return ApiResponse::success(['deleted'=>true]);
 }
 public function addDependency(Request $request,int $id,TaskDependencyService $service):JsonResponse {
  $task=$this->task($request,$id,true);$data=$request->validate(['depends_on_task_id'=>'required|integer']);
  $other=Task::accessibleTo($request->user())->where('household_id',$task->household_id)->findOrFail($data['depends_on_task_id']);
  $service->add($task,$other);TaskActivityService::record($task,$request->user()->id,'dependency_added',['task_id'=>$other->id]);return ApiResponse::success(['task'=>$this->formatted($task)]);
 }
 public function removeDependency(Request $request,int $id,int $dependencyId):JsonResponse {
  $task=$this->task($request,$id,true);$task->dependencies()->detach($dependencyId);TaskActivityService::record($task,$request->user()->id,'dependency_removed',['task_id'=>$dependencyId]);return ApiResponse::success(['task'=>$this->formatted($task)]);
 }
 public function addReminder(Request $request,int $id):JsonResponse {
  $task=$this->task($request,$id,true);$data=$request->validate(['remind_at'=>'required|date|after:now']);
  if(in_array($task->status,['completed','skipped'],true))throw ValidationException::withMessages(['remind_at'=>'Completed tasks cannot receive reminders.']);
  if($task->due_at && \Carbon\Carbon::parse($data['remind_at'])->greaterThan($task->due_at))throw ValidationException::withMessages(['remind_at'=>'Reminder cannot be later than the due date.']);
  $reminder=$task->reminders()->create(['user_id'=>$request->user()->id,'remind_at'=>$data['remind_at'],'status'=>'scheduled']);
  TaskActivityService::record($task,$request->user()->id,'reminder_created',['reminder_id'=>$reminder->id]);return ApiResponse::success(['reminder'=>$reminder],201);
 }
 public function snoozeReminder(Request $request,int $id,int $reminderId):JsonResponse {
  $task=$this->task($request,$id,true);$reminder=$task->reminders()->findOrFail($reminderId);
  $data=$request->validate(['until'=>'required|date|after:now']);
  if(in_array($task->status,['completed','skipped'],true))throw ValidationException::withMessages(['until'=>'Task is already closed.']);
  if($task->due_at && \Carbon\Carbon::parse($data['until'])->greaterThan($task->due_at))throw ValidationException::withMessages(['until'=>'Snooze cannot extend past the due date.']);
  $reminder->update(['snoozed_until'=>$data['until'],'status'=>'scheduled','sent_at'=>null]);TaskActivityService::record($task,$request->user()->id,'reminder_snoozed',['reminder_id'=>$reminderId]);return ApiResponse::success(['reminder'=>$reminder]);
 }
 public function timeline(Request $request,int $id):JsonResponse {return ApiResponse::success(['activities'=>$this->task($request,$id)->activities()->with('user:id,name')->paginate(40)]);}
 public function dueReminders(Request $request):JsonResponse {
  $h=$this->household($request);
  $reminders=Reminder::whereHas('task',fn($q)=>$q->where('household_id',$h)->whereNotIn('status',['completed','skipped']))->where('user_id',$request->user()->id)
   ->where('status','sent')->orderByDesc('sent_at')->limit(50)->get();
  return ApiResponse::success(['reminders'=>$reminders]);
 }
}
