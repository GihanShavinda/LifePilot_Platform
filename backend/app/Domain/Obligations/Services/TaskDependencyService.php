<?php
namespace App\Domain\Obligations\Services;
use App\Domain\Obligations\Models\Task;
use Illuminate\Validation\ValidationException;
class TaskDependencyService {
 public function add(Task $task, Task $prerequisite): void {
  if($task->household_id !== $prerequisite->household_id) throw ValidationException::withMessages(['depends_on_task_id'=>'Cross-household dependencies are forbidden.']);
  if($task->id === $prerequisite->id || $this->reaches($prerequisite,$task->id,[])) throw ValidationException::withMessages(['depends_on_task_id'=>'Dependency would introduce a cycle.']);
  $task->dependencies()->syncWithoutDetaching([$prerequisite->id]);
 }
 private function reaches(Task $node,int $target,array $visited):bool {
  if($node->id===$target)return true;
  if(isset($visited[$node->id]))return false;
  $visited[$node->id]=true;
  foreach($node->dependencies as $dep)if($this->reaches($dep,$target,$visited))return true;
  return false;
 }
 public function ensureReady(Task $task):void {
  if($task->dependencies()->whereNotIn('status',['completed'])->exists()) throw ValidationException::withMessages(['status'=>'Complete prerequisite tasks first.']);
 }
}
