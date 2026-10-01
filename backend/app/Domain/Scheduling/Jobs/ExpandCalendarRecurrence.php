<?php
namespace App\Domain\Scheduling\Jobs;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Scheduling\Services\CalendarRecurrence;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Carbon\CarbonImmutable;
class ExpandCalendarRecurrence implements ShouldQueue {
 use Queueable;
 public function __construct(public int $eventId){}
 public function handle(CalendarRecurrence $recurrence):void {
  $root=CalendarEvent::find($this->eventId);if(!$root||!$root->recurrence_frequency||!$root->series_key)return;
  $horizon=CarbonImmutable::now()->addDays(config('lifepilot_scheduling.horizon_days',45));$end=$root->recurrence_until && $root->recurrence_until->lt($horizon)?CarbonImmutable::parse($root->recurrence_until):$horizon;
  $starts=$recurrence->occurrences(CarbonImmutable::parse($root->starts_at),$root->recurrence_frequency,$root->recurrence_interval,$end);
  foreach($starts as $start){if($start->equalTo(CarbonImmutable::parse($root->starts_at)))continue;
   CalendarEvent::firstOrCreate(['series_key'=>$root->series_key,'occurrence_at'=>$start],array_merge($root->only(['household_id','user_id','task_id','document_id','title','description','location','timezone','status','reminder_offsets']),['starts_at'=>$start,'ends_at'=>$start->addSeconds($root->starts_at->diffInSeconds($root->ends_at)),'recurrence_frequency'=>null]));
  }
 }
}
