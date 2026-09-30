<?php
namespace App\Domain\Finance\Services;
use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
class FinanceAccess {
 public function household(Request $request,bool $write=false):int {
  $membership=$request->user()->householdMemberships()->first();
  if(!$membership)throw new AuthorizationException('Household required.');
  $role=$membership->role instanceof \BackedEnum?$membership->role->value:(string)$membership->role;
  if($write&&!in_array($role,['owner','family_member'],true))throw new AuthorizationException('Read-only role.');
  return (int)$membership->household_id;
 }
 public function requireDocument(int $householdId,?int $documentId):void {
  if($documentId!==null)\App\Domain\Documents\Models\Document::query()->where('household_id',$householdId)->findOrFail($documentId);
 }
}
