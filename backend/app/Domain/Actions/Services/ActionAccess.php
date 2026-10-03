<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Models\ActionPlan;
use App\Domain\Users\Models\User;

class ActionAccess
{
    public function householdId(User $user): int
    {
        $membership = $user->householdMemberships()->first();
        abort_unless($membership, 403, 'Household membership is required.');
        return (int) $membership->household_id;
    }

    public function plan(User $user, int $id): ActionPlan
    {
        $householdId = $this->householdId($user);

        return ActionPlan::query()
            ->whereKey($id)
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->with(['steps.approvals', 'executions.result'])
            ->firstOrFail();
    }
}
