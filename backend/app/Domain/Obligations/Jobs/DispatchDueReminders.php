<?php

namespace App\Domain\Obligations\Jobs;

use App\Domain\Obligations\Models\Reminder;
use App\Domain\Obligations\Services\TaskActivityService;
use App\Domain\Scheduling\Services\NotificationEngine;
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DispatchDueReminders implements ShouldQueue
{
  use Queueable;
  public function handle(NotificationEngine $notifications): void
  {
    Reminder::where('status', 'scheduled')->where('remind_at', '<=', now())->where(fn($q) => $q->whereNull('snoozed_until')->orWhere('snoozed_until', '<=', now()))->with('task')->chunkById(100, function ($batch) use ($notifications) {
      foreach ($batch as $reminder) {
        $task = $reminder->task;
        if (!$task || $task->trashed() || in_array($task->status, ['completed', 'skipped'], true)) {
          $reminder->update(['status' => 'cancelled']);
          continue;
        }
        $user = User::find($reminder->user_id);
        if (!$user) continue;
        $preference = \App\Domain\Notifications\Models\NotificationPreference::where('user_id', $user->id)->first();
        if ($preference && !$preference->reminder_enabled) {
          $reminder->update(['status' => 'cancelled']);
          continue;
        }
        $notifications->scheduleTemplate($user, $task->household_id, 'reminder', ['title' => $task->title], "reminder:$reminder->id:" . ($reminder->snoozed_until?->timestamp ?? $reminder->remind_at->timestamp), CarbonImmutable::now(), $task->id, null, $reminder->id);
        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
        TaskActivityService::record($task, null, 'reminder_sent', ['reminder_id' => $reminder->id]);
      }
    });
  }
}
