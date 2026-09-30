<?php
namespace App\Domain\Finance\Jobs;
use App\Domain\Finance\Models\{Subscription,Warranty,MaintenanceRecord};
use App\Domain\Finance\Services\LifeFinanceReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
class RefreshLifeFinanceReminders implements ShouldQueue {
 use Queueable;
 public function handle(LifeFinanceReminderService $service):void {
  Warranty::where('status','!=','void')->whereDate('end_date','<',today()->toDateString())->update(['status'=>'expired']);
  Warranty::where('status','!=','void')->whereBetween('end_date',[today()->toDateString(),today()->addDays(30)->toDateString()])->update(['status'=>'expiring']);
  Warranty::where('status','!=','void')->whereDate('end_date','>',today()->addDays(30)->toDateString())->update(['status'=>'active']);
  Subscription::where('status','active')->whereDate('next_billing_date','>=',today()->toDateString())->chunkById(100,fn($subs)=>$subs->each(fn($s)=>$service->subscription($s)));
  Warranty::with('asset')->whereNull('reminder_task_id')->whereDate('end_date','>=',today()->toDateString())->chunkById(100,fn($items)=>$items->each(fn($w)=>$service->warranty($w,$w->asset->user_id)));
  MaintenanceRecord::with('asset')->whereNull('reminder_task_id')->whereDate('next_due_date','>=',today()->toDateString())->chunkById(100,fn($items)=>$items->each(fn($m)=>$service->maintenance($m,$m->asset->user_id)));
 }
}
