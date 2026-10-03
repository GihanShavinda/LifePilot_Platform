<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Enums\{ActionPlanStatus, ActionRiskLevel, ActionStepStatus};
use App\Domain\Actions\Models\{ActionApproval, ActionPlan};
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActionApprovalService
{
    public function approve(User $user, ActionPlan $plan, array $data): ActionPlan
    {
        if (in_array($plan->status, [ActionPlanStatus::Completed, ActionPlanStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['plan' => 'This action plan can no longer be approved.']);
        }

        $requestedStepIds = array_map('intval', $data['step_ids'] ?? $plan->steps->pluck('id')->all());
        $highRiskAck = (bool) ($data['high_risk_ack'] ?? false);
        $comment = $data['comment'] ?? null;

        return DB::transaction(function () use ($user, $plan, $requestedStepIds, $highRiskAck, $comment) {
            $approvedCount = 0;

            foreach ($plan->steps as $step) {
                if (!in_array($step->id, $requestedStepIds, true)) {
                    ActionApproval::updateOrCreate(
                        [
                            'action_plan_id' => $plan->id,
                            'action_step_id' => $step->id,
                            'user_id' => $user->id,
                        ],
                        [
                            'decision' => 'rejected',
                            'explicit_high_risk_ack' => false,
                            'comment' => $comment,
                            'decided_at' => now(),
                        ]
                    );
                    $step->update(['status' => ActionStepStatus::Rejected]);
                    continue;
                }

                $isHigh = $step->risk_level === ActionRiskLevel::High;
                $policySnapshot = $step->policy_snapshot ?? [];
                $requiresExplicit = (bool) ($policySnapshot['requires_explicit_high_risk_ack'] ?? $isHigh);

                if ($isHigh && $requiresExplicit && !$highRiskAck) {
                    throw ValidationException::withMessages([
                        'high_risk_ack' => 'High-risk actions require explicit acknowledgement before approval.',
                    ]);
                }

                ActionApproval::updateOrCreate(
                    [
                        'action_plan_id' => $plan->id,
                        'action_step_id' => $step->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'decision' => 'approved',
                        'explicit_high_risk_ack' => $isHigh ? $highRiskAck : false,
                        'comment' => $comment,
                        'decided_at' => now(),
                    ]
                );

                $step->update(['status' => ActionStepStatus::Approved]);
                $approvedCount++;
            }

            if ($approvedCount === 0) {
                throw ValidationException::withMessages(['step_ids' => 'At least one action step must be approved.']);
            }

            $status = $approvedCount === $plan->steps->count()
                ? ActionPlanStatus::Approved
                : ActionPlanStatus::PartiallyApproved;

            $plan->update([
                'status' => $status,
                'approved_at' => now(),
            ]);

            return $plan->fresh(['steps.approvals', 'approvals']);
        });
    }
}
