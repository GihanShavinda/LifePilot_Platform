<?php
namespace App\Domain\Obligations\Jobs;
use App\Domain\Obligations\Services\RecurrenceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class GenerateRecurringTasks implements ShouldQueue {
 use Queueable;
 public function handle(RecurrenceService $service):void {$service->generateDue();}
}
