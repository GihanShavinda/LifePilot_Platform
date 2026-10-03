<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Enums\{ActionRiskLevel, ActionType};
use App\Domain\Actions\Models\ActionPolicy;
use Illuminate\Validation\ValidationException;

class ActionPolicyEngine
{
    public function policy(ActionType $type): ActionPolicy
    {
        $defaults = $this->defaults()[$type->value] ?? null;

        if (!$defaults) {
            throw ValidationException::withMessages([
                'action_type' => "Unsupported action type: {$type->value}.",
            ]);
        }

        return ActionPolicy::firstOrCreate(
            ['action_type' => $type->value],
            $defaults
        );
    }

    public function validatePayload(ActionType $type, array $payload): void
    {
        if ($this->looksLikeFinancialExecution($type, $payload)) {
            throw ValidationException::withMessages([
                'action' => 'Financial transaction execution is out of scope for LifePilot P9.',
            ]);
        }

        if ($type === ActionType::RequestIntegrationAction) {
            $operation = strtolower((string) ($payload['operation'] ?? ''));
            if (str_contains($operation, 'pay') || str_contains($operation, 'purchase') || str_contains($operation, 'transfer')) {
                throw ValidationException::withMessages([
                    'action' => 'Financial integration actions are out of scope.',
                ]);
            }
        }
    }

    public function overallRisk(iterable $steps): ActionRiskLevel
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        $highest = ActionRiskLevel::Low;

        foreach ($steps as $step) {
            $risk = $step->risk_level instanceof ActionRiskLevel
                ? $step->risk_level
                : ActionRiskLevel::from((string) $step->risk_level);

            if ($rank[$risk->value] > $rank[$highest->value]) {
                $highest = $risk;
            }
        }

        return $highest;
    }

    private function looksLikeFinancialExecution(ActionType $type, array $payload): bool
    {
        if ($type !== ActionType::RequestIntegrationAction) {
            return false;
        }

        $joined = strtolower(json_encode($payload) ?: '');
        foreach (['financial_action', 'make_payment', 'pay_bill', 'bank_transfer', 'purchase', 'charge_card'] as $blocked) {
            if (str_contains($joined, $blocked)) {
                return true;
            }
        }
        return false;
    }

    private function defaults(): array
    {
        $approval = true;

        return [
            ActionType::CreateTask->value => [
                'risk_level' => ActionRiskLevel::Low->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['internal_only' => true],
                'version' => 1,
            ],
            ActionType::UpdateTask->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['household_scoped' => true],
                'version' => 1,
            ],
            ActionType::CreateReminder->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['internal_only' => true],
                'version' => 1,
            ],
            ActionType::CreateCalendarEvent->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['internal_calendar_only' => true],
                'version' => 1,
            ],
            ActionType::PrepareEmailDraft->value => [
                'risk_level' => ActionRiskLevel::Low->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['send_forbidden' => true],
                'version' => 1,
            ],
            ActionType::CategorizeExpense->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['household_scoped' => true],
                'version' => 1,
            ],
            ActionType::CreateExpense->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['record_only' => true, 'no_payment_execution' => true],
                'version' => 1,
            ],
            ActionType::CreateAsset->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['internal_only' => true],
                'version' => 1,
            ],
            ActionType::CreateSubscription->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['record_only' => true, 'no_external_signup' => true],
                'version' => 1,
            ],
            ActionType::ArchiveDocument->value => [
                'risk_level' => ActionRiskLevel::Medium->value,
                'requires_approval' => $approval,
                'requires_explicit_high_risk_ack' => false,
                'enabled' => true,
                'conditions' => ['soft_archive_only' => true],
                'version' => 1,
            ],
            ActionType::RequestIntegrationAction->value => [
                'risk_level' => ActionRiskLevel::High->value,
                'requires_approval' => true,
                'requires_explicit_high_risk_ack' => true,
                'enabled' => true,
                'conditions' => [
                    'execution_mode' => 'prepare_request_only',
                    'financial_actions_forbidden' => true,
                ],
                'version' => 1,
            ],
        ];
    }
}
