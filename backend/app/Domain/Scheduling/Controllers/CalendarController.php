<?php
namespace App\Domain\Scheduling\Controllers;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Scheduling\Services\{CalendarAccess,CalendarService};
use App\Domain\Scheduling\Events\LifePilotUpdated;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class CalendarController {
 public function __construct(private CalendarAccess $access,private CalendarService $calendar){}
 private function rules(bool $create=true):array{return [
  'title'=>[$create?'required':'sometimes','string','max:255'],'description'=>'nullable|string|max:5000','location'=>'nullable|string|max:255',
  'starts_at'=>[$create?'required':'sometimes','date'],'ends_at'=>[$create?'required':'sometimes','date'],'timezone'=>[$create?'required':'sometimes','timezone'],
  'task_id'=>'nullable|integer','document_id'=>'nullable|integer','reminder_offsets'=>'nullable|array|max:10','reminder_offsets.*'=>'integer|min:0|max:365',
  'recurrence_frequency'=>['nullable',Rule::in(['daily','weekly','monthly','yearly'])],'recurrence_interval'=>'integer|min:1|max:365','recurrence_until'=>'nullable|date',
  ];}
 public function index(Request $r):JsonResponse{
  $h=$this->access->household($r->user());$data=$r->validate(['from'=>'required|date','to'=>'required|date|after:from']);
  $events=CalendarEvent::where('household_id',$h)->where('starts_at','<',$data['to'])->where('ends_at','>',$data['from'])->orderBy('starts_at')->limit(1000)->get();
  return ApiResponse::success(['events'=>$events]);
 }
 public function show(Request $r,int $id):JsonResponse{return ApiResponse::success(['event'=>$this->access->event($r->user(),$id)]);}
 public function conflicts(Request $r):JsonResponse{
  $this->access->household($r->user());$d=$r->validate(['starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','timezone'=>'required|timezone','except_id'=>'sometimes|integer']);
  return ApiResponse::success(['conflicts'=>$this->calendar->conflicts($r->user()->id,CarbonImmutable::parse($d['starts_at'],$d['timezone']),CarbonImmutable::parse($d['ends_at'],$d['timezone']),$d['except_id']??null)]);
 }
 public function store(Request $r):JsonResponse{
  $h=$this->access->household($r->user(),true);$d=$r->validate($this->rules());
  if(!empty($d['task_id']))$this->access->task($r->user(),$d['task_id'],$h);
  if(!empty($d['document_id']))$this->access->document($r->user(),$d['document_id'],$h);
  $start=CarbonImmutable::parse($d['starts_at'],$d['timezone']);$end=CarbonImmutable::parse($d['ends_at'],$d['timezone']);
  if($end->lte($start))throw ValidationException::withMessages(['ends_at'=>'End must be later than start.']);
  $conflicts=$this->calendar->conflicts($r->user()->id,$start,$end);
  if($conflicts&&!$r->boolean('confirm_conflicts'))return response()->json(['success'=>false,'error'=>['code'=>'CALENDAR_CONFLICT','message'=>'Review overlapping events before confirming.','details'=>['conflicts'=>$conflicts]]],409);
  $event=$this->calendar->create($d,$h,$r->user()->id);
  event(new LifePilotUpdated($r->user()->id,'calendar.event.updated',['event_id'=>$event->id]));
  return ApiResponse::success(['event'=>$event,'conflicts'=>$conflicts],201);
 }
 public function update(Request $r,int $id):JsonResponse{
  $event=$this->access->event($r->user(),$id,true);$d=$r->validate($this->rules(false));
  if(isset($d['task_id']))$this->access->task($r->user(),$d['task_id'],$event->household_id);
  if(isset($d['document_id']))$this->access->document($r->user(),$d['document_id'],$event->household_id);
  $timezone=$d['timezone']??$event->timezone;
  $start=isset($d['starts_at'])?CarbonImmutable::parse($d['starts_at'],$timezone):CarbonImmutable::parse($event->starts_at);
  $end=isset($d['ends_at'])?CarbonImmutable::parse($d['ends_at'],$timezone):CarbonImmutable::parse($event->ends_at);
  if($end->lte($start))throw ValidationException::withMessages(['ends_at'=>'End must be later than start.']);
  $conflicts=$this->calendar->conflicts($r->user()->id,$start,$end,$event->id);
  if($conflicts&&!$r->boolean('confirm_conflicts'))return response()->json(['success'=>false,'error'=>['code'=>'CALENDAR_CONFLICT','message'=>'Review overlapping events.','details'=>['conflicts'=>$conflicts]]],409);
  // Recurring series edits must not silently rewrite already generated occurrences.
  if($event->recurrence_frequency && (!$start->equalTo(\Carbon\CarbonImmutable::parse($event->starts_at))||!$end->equalTo(\Carbon\CarbonImmutable::parse($event->ends_at))))throw ValidationException::withMessages(['recurrence'=>'Create a new series to change the recurrence anchor.']);
  if(!empty($d['recurrence_frequency'])||!empty($d['recurrence_until']))throw ValidationException::withMessages(['recurrence_frequency'=>'Change recurrence by creating a new series.']);
  unset($d['recurrence_frequency'],$d['recurrence_until'],$d['recurrence_interval']);
  $d['starts_at']=$start->utc();$d['ends_at']=$end->utc();$event->update($d);
  event(new LifePilotUpdated($r->user()->id,'calendar.event.updated',['event_id'=>$event->id]));
  return ApiResponse::success(['event'=>$event,'conflicts'=>$conflicts]);
 }
 public function destroy(Request $r,int $id):JsonResponse{
  $event=$this->access->event($r->user(),$id,true);$event->update(['status'=>'cancelled']);$event->delete();
  \App\Domain\Scheduling\Models\Notification::where('calendar_event_id',$event->id)->where('status','pending')->update(['status'=>'cancelled']);
  event(new LifePilotUpdated($r->user()->id,'calendar.event.updated',['event_id'=>$event->id]));return ApiResponse::success(['deleted'=>true]);
 }
 public function fromTask(Request $r,int $taskId):JsonResponse{
  $h=$this->access->household($r->user(),true);$task=\App\Domain\Obligations\Models\Task::where('household_id',$h)->findOrFail($taskId);
  $d=$r->validate(['timezone'=>'required|timezone','duration_minutes'=>'sometimes|integer|min:5|max:1440','confirm_conflicts'=>'sometimes|boolean']);
  if(!$task->due_at)throw ValidationException::withMessages(['task'=>'Set a task due date before creating an event.']);
  $start=CarbonImmutable::parse($task->due_at)->subMinutes($d['duration_minutes']??60);$end=CarbonImmutable::parse($task->due_at);
  $conflicts=$this->calendar->conflicts($r->user()->id,$start,$end);if($conflicts&&!($d['confirm_conflicts']??false))return response()->json(['success'=>false,'error'=>['code'=>'CALENDAR_CONFLICT','details'=>['conflicts'=>$conflicts]]],409);
  $event=$this->calendar->create(['title'=>$task->title,'description'=>$task->description,'starts_at'=>$start,'ends_at'=>$end,'timezone'=>$d['timezone'],'task_id'=>$task->id,'document_id'=>$task->document_id,'reminder_offsets'=>[1,0]],$h,$r->user()->id);
  return ApiResponse::success(['event'=>$event],201);
 }
 public function fromDocument(Request $r,int $documentId):JsonResponse{
  $h=$this->access->household($r->user(),true);$this->access->document($r->user(),$documentId,$h);
  $d=$r->validate(['timezone'=>'required|timezone','starts_at'=>'required|date','ends_at'=>'required|date|after:starts_at','title'=>'required|string|max:255','confirm_conflicts'=>'sometimes|boolean']);
  $ex=\App\Domain\Documents\Models\DocumentExtraction::where('document_id',$documentId)->orderByDesc('version_number')->firstOrFail();
  $accepted=$ex->fields()->where('field_name','document_type')->whereIn('review_status',['accepted','edited'])->get();
  if(!$accepted->contains(fn($f)=>in_array(strtolower(trim((string)$f->normalized_value,'"')),['appointment'],true)))throw ValidationException::withMessages(['document'=>'Appointment document classification must be reviewed and accepted first.']);
  // User explicitly chooses the event dates; never silently use an unreviewed extracted date.
  $start=CarbonImmutable::parse($d['starts_at'],$d['timezone']);$end=CarbonImmutable::parse($d['ends_at'],$d['timezone']);
  $conflicts=$this->calendar->conflicts($r->user()->id,$start,$end);if($conflicts&&!($d['confirm_conflicts']??false))return response()->json(['success'=>false,'error'=>['code'=>'CALENDAR_CONFLICT','details'=>['conflicts'=>$conflicts]]],409);
  $event=$this->calendar->create(['title'=>$d['title'],'starts_at'=>$start,'ends_at'=>$end,'timezone'=>$d['timezone'],'document_id'=>$documentId,'reminder_offsets'=>[7,1,0]],$h,$r->user()->id);
  return ApiResponse::success(['event'=>$event],201);
 }
}
