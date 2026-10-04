<?php

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Enums\InvitationStatus;
use App\Domain\Collaboration\Models\HouseholdInvitation;
use App\Domain\Collaboration\Services\{HouseholdAccessService, HouseholdActivityService};
use App\Domain\Users\Models\HouseholdMember;
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InvitationController
{
    public function __construct(
        private HouseholdAccessService $access,
        private HouseholdActivityService $activity,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $this->access->ensureManageMembers($request->user(), $householdId);

        return ApiResponse::success([
            'invitations' => HouseholdInvitation::query()
                ->where('household_id', $householdId)
                ->with('inviter:id,name,email')
                ->latest()
                ->paginate(30),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $this->access->ensureManageMembers($request->user(), $householdId);

        $data = $request->validate([
            'email' => 'required|email|max:255',
            'role' => ['required', Rule::in(['admin', 'member', 'viewer'])],
        ]);

        $email = mb_strtolower(trim($data['email']));
        $existingUserId = \App\Domain\Users\Models\User::query()->whereRaw('LOWER(email) = ?', [$email])->value('id');
        if ($existingUserId && HouseholdMember::where(['household_id' => $householdId, 'user_id' => $existingUserId])->exists()) {
            throw ValidationException::withMessages(['email' => 'This user is already a household member.']);
        }

        $rawToken = Str::random(64);
        $invitation = HouseholdInvitation::updateOrCreate(
            ['household_id' => $householdId, 'email' => $email, 'status' => InvitationStatus::Pending],
            [
                'invited_by_user_id' => $request->user()->id,
                'role' => $data['role'],
                'token_hash' => hash('sha256', $rawToken),
                'expires_at' => now()->addDays(7),
                'responded_at' => null,
            ]
        );

        $this->activity->record($householdId, $request->user(), 'member.invited', 'A household invitation was created.', 'household_invitation', $invitation->id, ['email' => $email, 'role' => $data['role']], true);

        return ApiResponse::success([
            'invitation' => $invitation,
            // Returned once so the local application can construct the acceptance link.
            // Production email delivery can consume this token without persisting plaintext.
            'accept_token' => $rawToken,
        ], 201);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        return $this->respond($request, $token, true);
    }

    public function decline(Request $request, string $token): JsonResponse
    {
        return $this->respond($request, $token, false);
    }

    private function respond(Request $request, string $token, bool $accept): JsonResponse
    {
        $hash = hash('sha256', $token);
        $invitation = HouseholdInvitation::query()
            ->where('token_hash', $hash)
            ->where('status', InvitationStatus::Pending)
            ->firstOrFail();

        if ($invitation->expires_at->isPast()) {
            $invitation->update(['status' => InvitationStatus::Expired, 'responded_at' => now()]);
            throw ValidationException::withMessages(['invitation' => 'This invitation has expired.']);
        }

        if (mb_strtolower($request->user()->email) !== mb_strtolower($invitation->email)) {
            throw ValidationException::withMessages(['invitation' => 'This invitation belongs to a different email address.']);
        }

        return DB::transaction(function () use ($request, $invitation, $accept) {
            if (!$accept) {
                $invitation->update(['status' => InvitationStatus::Declined, 'responded_at' => now()]);
                $this->activity->record($invitation->household_id, $request->user(), 'member.invitation_declined', 'A household invitation was declined.', 'household_invitation', $invitation->id);
                return ApiResponse::success(['accepted' => false]);
            }

            HouseholdMember::updateOrCreate(
                ['household_id' => $invitation->household_id, 'user_id' => $request->user()->id],
                ['role' => $invitation->role]
            );

            $invitation->update(['status' => InvitationStatus::Accepted, 'responded_at' => now()]);
            $this->activity->record($invitation->household_id, $request->user(), 'member.joined', $request->user()->name.' joined the household.', 'user', $request->user()->id, ['role' => $invitation->role], true);

            return ApiResponse::success(['accepted' => true, 'household_id' => $invitation->household_id, 'role' => $invitation->role]);
        });
    }
}
