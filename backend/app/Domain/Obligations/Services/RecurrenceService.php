<?php
namespace App\Domain\Obligations\Services;
use App\Domain\Obligations\Models\{RecurringRule,Task};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
class RecurrenceService {
 public function next(CarbonImmutable $date, string $frequency, int $interval): CarbonImmutable {
  return match($frequency) {
   'daily'=>$date->addDays($interval), 'weekly'=>$date->addWeeks($interval),
   'monthly'=>$date->addMonthsNoOverflow($interval), 'yearly'=>$date->addYearsNoOverflow($interval),
   default=>throw new \InvalidArgumentException('Unsupported recurrence frequency'),
  };
 }
 public function generateDue(): int {
  $count=0;
  RecurringRule::where('enabled',true)->where('next_at','<=',now())->orderBy('id')->chunkById(50,function($rules) use (&$count) {
   foreach($rules as $rule) {
    DB::transaction(function() use($rule,&$count) {
     $locked=RecurringRule::whereKey($rule->id)->lockForUpdate()->first();
     if(!$locked || !$locked->enabled || !$locked->next_at || $locked->next_at->isFuture())return;
     $base=Task::where('recurring_rule_id',$locked->id)->orderBy('occurrence_number')->first();
     if(!$base){$locked->update(['enabled'=>false]);return;}
     $next=CarbonImmutable::instance($locked->next_at);
     if(($locked->until_at && $next->greaterThan($locked->until_at)) || ($locked->max_occurrences && $locked->generated_occurrences >= $locked->max_occurrences)) {$locked->update(['enabled'=>false]);return;}
     $index=$locked->generated_occurrences+1;
     $task=Task::firstOrCreate(['recurring_rule_id'=>$locked->id,'occurrence_number'=>$index],[
       'household_id'=>$base->household_id,'user_id'=>$base->user_id,'obligation_id'=>null,'document_id'=>$base->document_id,
       'title'=>$base->title,'description'=>$base->description,'priority'=>$base->priority,'labels'=>$base->labels,'due_at'=>$next,
       'status'=>'pending',
     ]);
     if($task->wasRecentlyCreated){TaskActivityService::record($task,null,'recurrence_generated');app(ReminderService::class)->recommend($task);$count++;}
     $locked->update(['generated_occurrences'=>$index,'next_at'=>$this->next($next,$locked->frequency,$locked->interval)]);
    });
   }
  });
  return $count;
 }
}
