<?php
namespace App\Domain\Obligations\Services;
use App\Domain\Obligations\Models\Task;
use Carbon\CarbonImmutable;
class ReminderService {
 /** Recommendations are suggestions until a task exists and is approved. */
 public function offsets(Task $task): array {
  if (!$task->due_at) return [];
  $remaining = now()->diffInDays($task->due_at, false);
  return $remaining >= 7 ? [7,2,0] : ($remaining >= 2 ? [2,0] : [0]);
 }
 public function recommend(Task $task): void {
  if (!$task->due_at || in_array($task->status, ['completed','skipped'], true)) return;
  foreach ($this->offsets($task) as $days) {
   $when = $task->due_at->copy()->subDays($days);
   if ($when->isPast()) continue;
   $task->reminders()->firstOrCreate(
    ['user_id'=>$task->user_id,'remind_at'=>$when,'channel'=>'in_app'],
    ['status'=>'scheduled','recommended'=>true]
   );
  }
 }
 public function cancel(Task $task): void { $task->reminders()->where('status','scheduled')->update(['status'=>'cancelled']); }
}
