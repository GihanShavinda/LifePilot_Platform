<?php
namespace App\Domain\Audit\Controllers;
use App\Domain\Audit\Models\AuditLog;
use App\Support\ApiResponse;
use App\Support\PaginationMeta;
use Illuminate\Http\Request;
class AuditLogController
{
 public function index(Request $request){$hid=$request->user()->householdMemberships()->value('household_id');$logs=AuditLog::query()->where('household_id',$hid)->latest('created_at')->paginate(min((int)$request->integer('per_page',20),100));return ApiResponse::success($logs->items(),200,PaginationMeta::from($logs));}
}
