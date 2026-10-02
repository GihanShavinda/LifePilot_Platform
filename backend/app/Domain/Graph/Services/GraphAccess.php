<?php
namespace App\Domain\Graph\Services;

use App\Domain\Users\Models\User;

class GraphAccess
{
    public function householdId(User $user):int
    {
        $membership=$user->householdMemberships()->first();
        abort_unless($membership,403,'Household membership is required.');
        return (int)$membership->household_id;
    }
}
