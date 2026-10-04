<?php
namespace App\Domain\Finance\Controllers;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Services\{FinanceAccess,RecurringExpenseService};
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
class RecurringExpenseController {
 public function __construct(private FinanceAccess $access,private RecurringExpenseService $service){}
 public function preview(Request $r,int $id):JsonResponse {$h=$this->access->household($r);$e=Expense::accessibleTo($r->user())->where('household_id',$h)->findOrFail($id);$this->access->ensureResource($r->user(),$e);return ApiResponse::success(['suggestion'=>$this->service->preview($e)]);}
 public function confirm(Request $r,int $id):JsonResponse {$h=$this->access->household($r,true);$e=Expense::accessibleTo($r->user())->where('household_id',$h)->findOrFail($id);$this->access->ensureResource($r->user(),$e,true);return ApiResponse::success(['expense'=>$this->service->confirm($e,$r->user()->id)],201);}
}
