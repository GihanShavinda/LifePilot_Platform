<?php
namespace App\Domain\Scheduling\Services;
use App\Domain\Users\Models\User;
use App\Domain\Scheduling\Models\CalendarEvent;
use Illuminate\Auth\Access\AuthorizationException;
class CalendarAccess {
 public function household(User $user,bool $write=false,?int $requested=null):int {
  $query=$user->householdMemberships();if($requested!==null)$query->where('household_id',$requested);
  $membership=$query->first();if(!$membership)throw new AuthorizationException('Household membership required.');
  $role=$membership->role instanceof \BackedEnum?$membership->role->value:(string)$membership->role;
  if($write && !in_array($role,['owner','family_member'],true))throw new AuthorizationException('Read-only household role.');
  return $membership->household_id;
 }
 public function event(User $user,int $id,bool $write=false):CalendarEvent {
  $householdIds=$user->householdMemberships()->pluck('household_id');
  $event=CalendarEvent::whereIn('household_id',$householdIds)->findOrFail($id);
  $this->household($user,$write,$event->household_id);return $event;
 }
 public function document(User $user,int $id,int $household):void{\App\Domain\Documents\Models\Document::where('household_id',$household)->findOrFail($id);}
 public function task(User $user,int $id,int $household):void{\App\Domain\Obligations\Models\Task::where('household_id',$household)->findOrFail($id);}
}
