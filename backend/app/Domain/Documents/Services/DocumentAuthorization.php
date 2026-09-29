<?php
namespace App\Domain\Documents\Services;
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
class DocumentAuthorization { public function householdId(User $user):int{$id=$user->householdMemberships()->value('household_id');if(!$id)throw new AuthorizationException('No household membership found.');return (int)$id;} public function ensure(User $user,Document $doc,bool $write=false):void{$membership=$user->householdMemberships()->where('household_id',$doc->household_id)->first();if(!$membership)throw new AuthorizationException('You cannot access this document.');$role=$membership->role instanceof \BackedEnum?$membership->role->value:(string)$membership->role;if($write && !in_array($role,['owner','family_member'],true))throw new AuthorizationException('Viewer role is read-only.');} }
