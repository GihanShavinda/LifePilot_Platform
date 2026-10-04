<?php

namespace App\Domain\Completion\Services;

use App\Domain\Users\Models\User;
use Illuminate\Validation\ValidationException;

class CompletionAccess
{
    public function householdId(User $user): int
    {
        $householdId = $user->householdMemberships()->orderBy('id')->value('household_id');

        if (!$householdId) {
            throw ValidationException::withMessages([
                'household' => 'You do not belong to a household.',
            ]);
        }

        return (int) $householdId;
    }
}
