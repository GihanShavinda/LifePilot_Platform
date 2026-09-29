<?php
namespace App\Domain\Audit\Services;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
class AuditService
{
 public function record(string $event, ?User $actor, ?Model $auditable=null, array $metadata=[], ?Request $request=null, ?int $householdId=null): AuditLog
 {
   $request ??= request();
   $householdId ??= $actor?->householdMemberships()->value('household_id');
   return AuditLog::create(['household_id'=>$householdId,'actor_user_id'=>$actor?->id,'event'=>$event,'auditable_type'=>$auditable?->getMorphClass(),'auditable_id'=>$auditable?->getKey(),'ip_address'=>$request?->ip(),'user_agent'=>substr((string)$request?->userAgent(),0,1000),'metadata'=>$metadata,'created_at'=>now()]);
 }
}
