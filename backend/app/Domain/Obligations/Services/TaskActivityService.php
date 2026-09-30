<?php
namespace App\Domain\Obligations\Services;
use App\Domain\Obligations\Models\{Task,TaskActivity};
class TaskActivityService {
 public static function record(Task $task, ?int $userId, string $event, array $details=[]): TaskActivity {
  return $task->activities()->create(['user_id'=>$userId,'event'=>$event,'details'=>$details]);
 }
}
