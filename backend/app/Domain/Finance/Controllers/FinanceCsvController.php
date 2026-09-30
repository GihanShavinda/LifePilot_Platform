<?php
namespace App\Domain\Finance\Controllers;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Services\{FinanceAccess,FinanceCsvService};
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
use Symfony\Component\HttpFoundation\StreamedResponse;
class FinanceCsvController {
 public function __construct(private FinanceAccess $access,private FinanceCsvService $csv){}
 public function import(Request $r):JsonResponse {
  $h=$this->access->household($r,true);$r->validate(['file'=>'required|file|mimes:csv,txt|max:2048']);
  return ApiResponse::success($this->csv->import($r->file('file')->getRealPath(),$h,$r->user()->id));
 }
 public function export(Request $r):StreamedResponse {
  $h=$this->access->household($r);$q=Expense::where('household_id',$h)->with(['merchant','category'])->orderBy('expense_date');
  if($r->filled('from'))$q->whereDate('expense_date','>=',$r->query('from'));if($r->filled('to'))$q->whereDate('expense_date','<=',$r->query('to'));
  return response()->streamDownload(function()use($q){$fp=fopen('php://output','w');fputcsv($fp,FinanceCsvService::HEADER);
   foreach($q->cursor() as $e){$safe=fn($v)=>is_string($v)&&preg_match('/^[=+@\t\r]/',$v)?"'".$v:$v;fputcsv($fp,[$e->expense_date->toDateString(),$safe($e->title),$e->amount,$e->currency,$safe($e->merchant?->name),$safe($e->category?->name),$safe($e->description)]);}fclose($fp);
  },'lifepilot-expenses.csv',['Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store']);
 }
}
