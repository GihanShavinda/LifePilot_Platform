<?php

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\{Assignment, HouseholdActivity, SharedResource};
use App\Domain\Collaboration\Services\{HouseholdAccessService, HouseholdActivityService};
use App\Domain\Users\Models\HouseholdMember;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HouseholdCollaborationController
{
    public function __construct(
        private HouseholdAccessService $access,
        private HouseholdActivityService $activity,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $membership = $this->access->membership($request->user());
        $householdId = (int) $membership->household_id;
        $role = $membership->role instanceof \BackedEnum ? $membership->role->value : (string) $membership->role;

        return ApiResponse::success([
            'household' => $membership->household()->first(['id', 'name']),
            'role' => $this->access->normalizedRole($role),
            'counts' => [
                'members' => HouseholdMember::where('household_id', $householdId)->count(),
                'shared_resources' => SharedResource::where('household_id', $householdId)->where('scope', 'household')->count(),
                'open_assignments' => Assignment::where('household_id', $householdId)->where('status', 'assigned')->count(),
            ],
            'members' => HouseholdMember::where('household_id', $householdId)->with('user:id,name,email')->orderBy('id')->get(),
            'recent_activity' => HouseholdActivity::where('household_id', $householdId)->with('actor:id,name,email')->latest()->limit(20)->get(),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        return ApiResponse::success([
            'activity' => HouseholdActivity::where('household_id', $householdId)->with('actor:id,name,email')->latest()->paginate(50),
        ]);
    }

    public function updateRole(Request $request, int $memberId): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $this->access->ensureManageMembers($request->user(), $householdId);
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'member', 'viewer'])]]);
        $target = HouseholdMember::where('household_id', $householdId)->findOrFail($memberId);

        $targetRole = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
        if ($targetRole === 'owner') {
            throw new AuthorizationException('The household owner role cannot be changed from this endpoint.');
        }

        $target->update(['role' => $data['role']]);
        $this->activity->record($householdId, $request->user(), 'member.role_changed', 'A household member role was changed.', 'user', $target->user_id, ['role' => $data['role']], true);
        return ApiResponse::success(['member' => $target->fresh('user:id,name,email')]);
    }

    public function removeMember(Request $request, int $memberId): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $this->access->ensureManageMembers($request->user(), $householdId);
        $target = HouseholdMember::where('household_id', $householdId)->findOrFail($memberId);

        $targetRole = $target->role instanceof \BackedEnum ? $target->role->value : (string) $target->role;
        $actorRole = $this->access->normalizedRole($this->access->role($request->user(), $householdId));

        if ($targetRole === 'owner') {
            throw new AuthorizationException('The household owner cannot be removed.');
        }
        if ($actorRole === 'admin' && $targetRole === 'admin') {
            throw new AuthorizationException('Only the owner may remove another admin.');
        }

        return DB::transaction(function () use ($request, $target, $householdId) {
            $removedUserId = (int) $target->user_id;
            Assignment::where('household_id', $householdId)->where('assignee_user_id', $removedUserId)->delete();
            $target->delete();

            $this->activity->record($householdId, $request->user(), 'member.removed', 'A member was removed from the household.', 'user', $removedUserId, [], true);
            return ApiResponse::success(['removed' => true, 'user_id' => $removedUserId]);
        });
    }
}
