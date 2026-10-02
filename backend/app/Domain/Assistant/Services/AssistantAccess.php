<?php
namespace App\Domain\Assistant\Services;
use App\Domain\Users\Models\User;
class AssistantAccess
{
    public function householdId(User $user):int
    {
        $membership=$user->householdMemberships()->first();
        abort_unless($membership,403,'Household membership is required.');
        return (int)$membership->household_id;
    }
}
