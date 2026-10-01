<?php
namespace App\Domain\Scheduling\Jobs;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Scheduling\Services\CalendarRecurrence;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\CarbonImmutable;
class GenerateCalendarOccurrences implements ShouldQueue {
 use Queueable;
 public function handle(CalendarRecurrence $recurrence):void {
  $horizon=now()->addDays(config('lifepilot_scheduling.horizon_days',45));
  CalendarEvent::whereNotNull('recurrence_frequency')->whereNull('deleted_at')->whereColumn('starts_at','occurrence_at')->chunkById(50,function($events)use($recurrence,$horizon){
   foreach($events as $root){if(!$root->series_key)continue;
    $limit=$root->recurrence_until && $root->recurrence_until->lt($horizon)?CarbonImmutable::parse($root->recurrence_until):CarbonImmutable::parse($horizon);
    $starts=$recurrence->occurrences(CarbonImmutable::parse($root->starts_at),$root->recurrence_frequency,$root->recurrence_interval,$limit,200);
    foreach($starts as $start){if($start->equalTo(CarbonImmutable::parse($root->starts_at)))continue;
     CalendarEvent::firstOrCreate(['series_key'=>$root->series_key,'occurrence_at'=>$start],array_merge($root->only(['household_id','user_id','task_id','document_id','title','description','location','timezone','status','reminder_offsets']),['starts_at'=>$start,'ends_at'=>$start->addSeconds($root->starts_at->diffInSeconds($root->ends_at)),'recurrence_frequency'=>null]));
    }
   }
  });
 }
}
