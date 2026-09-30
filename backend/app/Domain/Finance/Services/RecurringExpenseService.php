<?php
namespace App\Domain\Finance\Services;
use App\Domain\Finance\Models\Expense;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
class RecurringExpenseService {
 public function __construct(private FinanceCalculations $dates){}
 public function preview(Expense $expense):array {
  if(!$expense->recurrence_cycle||!$expense->next_occurrence_date)return ['eligible'=>false,'reason'=>'This expense is not recurring.'];
  return ['eligible'=>true,'source_expense_id'=>$expense->id,'next_date'=>$expense->next_occurrence_date->toDateString(),'amount'=>$expense->amount,'currency'=>$expense->currency,'notice'=>'Expected expense: confirm before recording as actual.'];
 }
 public function confirm(Expense $expense,int $userId):Expense {
  if(!$expense->recurrence_cycle||!$expense->next_occurrence_date)throw ValidationException::withMessages(['recurrence'=>'Expense is not recurring.']);
  return DB::transaction(function()use($expense,$userId){$e=Expense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
   $date=$e->next_occurrence_date->toDateString();
   $key=hash('sha256','recurring:'.$e->id.':'.$date);
   if(Expense::withTrashed()->where('household_id',$e->household_id)->where('import_hash',$key)->exists())throw ValidationException::withMessages(['recurrence'=>'Occurrence already recorded.']);
   $created=Expense::create(['household_id'=>$e->household_id,'user_id'=>$userId,'title'=>$e->title,'description'=>$e->description,'amount'=>$e->amount,'currency'=>$e->currency,'expense_category_id'=>$e->expense_category_id,'merchant_id'=>$e->merchant_id,'document_id'=>$e->document_id,'expense_date'=>$date,'source'=>'recurrence_confirmed','import_hash'=>$key]);
   $e->update(['next_occurrence_date'=>$this->dates->nextDate($date,$e->recurrence_cycle)]);
   return $created;
  });
 }
}
