<?php

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Models\Assignment;
use App\Domain\Collaboration\Services\{HouseholdAccessService, HouseholdActivityService, SharedResourceService};
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\HouseholdMember;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\Rule;

class AssignmentController
{
    public function __construct(
        private HouseholdAccessService $access,
        private SharedResourceService $sharing,
        private HouseholdActivityService $activity,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $query = Assignment::query()->where('household_id', $householdId)->with(['assignee:id,name,email', 'assignedBy:id,name,email']);

        if (!$this->access->canManageMembers($request->user(), $householdId)) {
            $query->where('assignee_user_id', $request->user()->id);
        }

        return ApiResponse::success(['assignments' => $query->latest('assigned_at')->paginate(50)]);
    }

    public function assignTask(Request $request, int $taskId): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $this->access->ensureWrite($request->user(), $householdId);
        $task = Task::accessibleTo($request->user())->where('household_id', $householdId)->findOrFail($taskId);
        $this->sharing->ensureWrite($request->user(), $task);

        $data = $request->validate([
            'assignee_user_id' => 'required|integer',
            'note' => 'nullable|string|max:2000',
        ]);

        $member = HouseholdMember::query()
            ->where('household_id', $householdId)
            ->where('user_id', $data['assignee_user_id'])
            ->firstOrFail();

        $role = $member->role instanceof \BackedEnum ? $member->role->value : (string) $member->role;
        if ($role === 'viewer') {
            throw new AuthorizationException('Viewer members cannot be assigned editable tasks.');
        }

        // Assignment makes the task explicitly household-visible.
        $this->sharing->share($request->user(), $task, \App\Domain\Collaboration\Enums\SharingScope::Household);

        $assignment = Assignment::updateOrCreate(
            ['household_id' => $householdId, 'assignable_type' => 'task', 'assignable_id' => $task->id],
            [
                'assignee_user_id' => $member->user_id,
                'assigned_by_user_id' => $request->user()->id,
                'status' => 'assigned',
                'note' => $data['note'] ?? null,
                'assigned_at' => now(),
                'completed_at' => null,
            ]
        );

        $this->activity->record($householdId, $request->user(), 'task.assigned', 'A shared task was assigned.', 'task', $task->id, ['assignee_user_id' => $member->user_id], true);

        return ApiResponse::success(['assignment' => $assignment->load('assignee:id,name,email')], 201);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $assignment = Assignment::where('household_id', $householdId)->findOrFail($id);
        $isAssignee = (int) $assignment->assignee_user_id === (int) $request->user()->id;
        if (!$isAssignee && !$this->access->canManageMembers($request->user(), $householdId)) {
            throw new AuthorizationException('Only the assignee or a household owner/admin can complete this assignment.');
        }

        $assignment->update(['status' => 'completed', 'completed_at' => now()]);
        $this->activity->record($householdId, $request->user(), 'assignment.completed', 'A household assignment was completed.', $assignment->assignable_type, $assignment->assignable_id);

        return ApiResponse::success(['assignment' => $assignment->fresh()]);
    }
}
