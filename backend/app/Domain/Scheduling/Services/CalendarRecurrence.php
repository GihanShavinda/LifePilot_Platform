<?php
namespace App\Domain\Scheduling\Services;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
class CalendarRecurrence {
 public function occurrences(CarbonImmutable $start,string $frequency,int $interval,CarbonImmutable $until,int $max=200):array {
  if($interval<1||$max<1||$max>500)throw ValidationException::withMessages(['recurrence'=>'Invalid recurrence.']);
  $occurrences=[];$next=$start;
  for($n=0;$n<$max && $next->lte($until);$n++){
   $occurrences[]=$next;
   $next=match($frequency){'daily'=>$next->addDays($interval),'weekly'=>$next->addWeeks($interval),'monthly'=>$start->addMonthsNoOverflow($interval*($n+1)),'yearly'=>$start->addYearsNoOverflow($interval*($n+1)),default=>throw ValidationException::withMessages(['recurrence_frequency'=>'Invalid frequency.'])};
  }
  return $occurrences;
 }
}
