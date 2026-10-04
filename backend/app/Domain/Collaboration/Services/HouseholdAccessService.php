<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class HouseholdAccessService
{
    public function membership(User $user, ?int $householdId = null): HouseholdMember
    {
        $query = $user->householdMemberships();
        if ($householdId !== null) {
            $query->where('household_id', $householdId);
        }

        $membership = $query->first();
        if (!$membership) {
            throw new AuthorizationException('Household membership required.');
        }

        return $membership;
    }

    public function householdId(User $user): int
    {
        return (int) $this->membership($user)->household_id;
    }

    public function role(User $user, int $householdId): string
    {
        $role = $this->membership($user, $householdId)->role;
        return $role instanceof \BackedEnum ? $role->value : (string) $role;
    }

    public function canWrite(User $user, int $householdId): bool
    {
        return in_array($this->normalizedRole($this->role($user, $householdId)), ['owner', 'admin', 'member'], true);
    }

    public function canManageMembers(User $user, int $householdId): bool
    {
        return in_array($this->normalizedRole($this->role($user, $householdId)), ['owner', 'admin'], true);
    }

    public function canInvite(User $user, int $householdId): bool
    {
        return $this->canManageMembers($user, $householdId);
    }

    public function ensureWrite(User $user, int $householdId): void
    {
        if (!$this->canWrite($user, $householdId)) {
            throw new AuthorizationException('Viewer role is read-only.');
        }
    }

    public function ensureManageMembers(User $user, int $householdId): void
    {
        if (!$this->canManageMembers($user, $householdId)) {
            throw new AuthorizationException('Owner or admin role required.');
        }
    }

    public function normalizedRole(string $role): string
    {
        return $role === 'family_member' ? 'member' : $role;
    }
}
