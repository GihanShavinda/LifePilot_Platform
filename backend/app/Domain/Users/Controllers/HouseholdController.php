<?php
namespace App\Domain\Users\Controllers;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
class HouseholdController
{
 public function current(Request $request){$membership=$request->user()->householdMemberships()->with('household')->firstOrFail();return ApiResponse::success(['household'=>['id'=>$membership->household->id,'name'=>$membership->household->name,'role'=>$membership->role->value]]);}
 public function members(Request $request){$membership=$request->user()->householdMemberships()->firstOrFail();$members=$membership->household->memberships()->with('user:id,name,email')->paginate(min((int)$request->integer('per_page',15),100));return ApiResponse::success($members->items(),200,['pagination'=>['current_page'=>$members->currentPage(),'per_page'=>$members->perPage(),'total'=>$members->total(),'last_page'=>$members->lastPage()]]);}
}
