<?php
namespace App\Domain\Auth\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class RequireHouseholdRole
{
 public function handle(Request $request,Closure $next,string ...$roles):Response
 {
   $membership=$request->user()?->householdMemberships()->first();
   abort_unless($membership && in_array($membership->role->value,$roles,true),403,'Your household role does not permit this action.');
   return $next($request);
 }
}
