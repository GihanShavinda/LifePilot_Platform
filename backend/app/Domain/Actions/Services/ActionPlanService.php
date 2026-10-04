<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Enums\{ActionPlanStatus, ActionStepStatus, ActionType};
use App\Domain\Actions\Models\{ActionPlan, ActionStep};
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ActionPlanService
{
    public function __construct(
        private readonly ActionAccess $access,
        private readonly ActionPolicyEngine $policies,
        private readonly ActionEvidenceValidator $evidence,
        private readonly ActionPreviewBuilder $previews,
    ) {
    }

    public function create(User $user, array $data): ActionPlan
    {
        $householdId = $this->access->householdId($user);
        $origin = $data['origin'] ?? 'user';
        $planEvidence = array_values(array_unique($data['evidence_refs'] ?? []));
        $this->evidence->requireEvidenceForAssistantOrigin($origin, $planEvidence);
        $this->evidence->validateReferences($user, $householdId, $planEvidence);

        $steps = $data['steps'] ?? [];
        if (!$steps) {
            throw ValidationException::withMessages(['steps' => 'At least one proposed action is required.']);
        }

        $idempotencyKey = $data['idempotency_key'] ?? hash('sha256', json_encode([
            $householdId,
            $user->id,
            $data['request'],
            $steps,
            $planEvidence,
        ]));

        $existing = ActionPlan::where('household_id', $householdId)
            ->where('idempotency_key', $idempotencyKey)
            ->with('steps')
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($user, $householdId, $origin, $planEvidence, $data, $steps, $idempotencyKey) {
            $plan = ActionPlan::create([
                'household_id' => $householdId,
                'user_id' => $user->id,
                'source_conversation_message_id' => $data['source_conversation_message_id'] ?? null,
                'request' => $data['request'],
                'origin' => $origin,
                'status' => ActionPlanStatus::Preview,
                'overall_risk' => 'low',
                'idempotency_key' => $idempotencyKey,
                'evidence_refs' => $planEvidence,
                'metadata' => $data['metadata'] ?? [],
            ]);

            foreach ($steps as $index => $input) {
                $type = ActionType::tryFrom((string) ($input['action_type'] ?? ''));
                if (!$type) {
                    throw ValidationException::withMessages(['steps' => 'Unsupported action type in plan.']);
                }

                $payload = $input['payload'] ?? [];
                $this->policies->validatePayload($type, $payload);
                $policy = $this->policies->policy($type);
                abort_unless($policy->enabled, 422, 'Action policy is disabled.');

                $refs = array_values(array_unique($input['evidence_refs'] ?? $planEvidence));
                $this->evidence->requireEvidenceForAssistantOrigin($origin, $refs);
                $this->evidence->validateReferences($user, $householdId, $refs);

                $sequence = $index + 1;
                $stepKey = hash('sha256', $plan->id . '|' . $sequence . '|' . $type->value . '|' . json_encode($payload));

                ActionStep::create([
                    'action_plan_id' => $plan->id,
                    'sequence' => $sequence,
                    'action_type' => $type,
                    'risk_level' => $policy->risk_level,
                    'status' => ActionStepStatus::Proposed,
                    'title' => $input['title'] ?? Str::headline($type->value),
                    'description' => $input['description'] ?? null,
                    'payload' => $payload,
                    'evidence_refs' => $refs,
                    'preview' => $this->previews->build($type, $payload),
                    'policy_snapshot' => [
                        'version' => $policy->version,
                        'conditions' => $policy->conditions,
                        'requires_explicit_high_risk_ack' => $policy->requires_explicit_high_risk_ack,
                    ],
                    'idempotency_key' => $stepKey,
                    'requires_approval' => $policy->requires_approval,
                    'external_effect' => $type === ActionType::RequestIntegrationAction,
                ]);
            }

            $plan->load('steps');
            $plan->update(['overall_risk' => $this->policies->overallRisk($plan->steps)]);

            return $plan->fresh('steps');
        });
    }
}
