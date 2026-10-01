<?php
namespace App\Domain\Scheduling\Services;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Scheduling\Jobs\ExpandCalendarRecurrence;
use App\Domain\Scheduling\Events\LifePilotUpdated;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CalendarService {
 public function __construct(private CalendarRecurrence $recurrence){}
 public function conflicts(int $user,CarbonImmutable $from,CarbonImmutable $to,?int $except=null):array{
  return CalendarEvent::where('user_id',$user)->where('status','confirmed')->where('starts_at','<',$to->utc())->where('ends_at','>',$from->utc())->when($except,fn($q)=>$q->where('id','!=',$except))->get(['id','title','starts_at','ends_at'])->toArray();
 }
 public function create(array $data,int $household,int $user):CalendarEvent{
  $timezone=$data['timezone']??'UTC';$start=CarbonImmutable::parse($data['starts_at'],$timezone)->utc();$end=CarbonImmutable::parse($data['ends_at'],$timezone)->utc();
  if($end->lte($start))throw ValidationException::withMessages(['ends_at'=>'Event must end after it starts.']);
  if(!empty($data['recurrence_until'])&&CarbonImmutable::parse($data['recurrence_until'],$timezone)->utc()->lt($start))throw ValidationException::withMessages(['recurrence_until'=>'Recurrence ends before event starts.']);
  $event=DB::transaction(function()use($data,$household,$user,$start,$end){
   $data['household_id']=$household;$data['user_id']=$user;$data['starts_at']=$start;$data['ends_at']=$end;
   if(!empty($data['recurrence_until']))$data['recurrence_until']=CarbonImmutable::parse($data['recurrence_until'],$data['timezone']??'UTC')->utc();
   if(!empty($data['recurrence_frequency'])){$data['series_key']=bin2hex(random_bytes(16));$data['occurrence_at']=$start;}
   return CalendarEvent::create($data);
  });
  if($event->recurrence_frequency)ExpandCalendarRecurrence::dispatch($event->id);
  return $event;
 }
}
