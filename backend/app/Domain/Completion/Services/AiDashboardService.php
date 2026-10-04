<?php

namespace App\Domain\Completion\Services;

use App\Domain\Actions\Models\ActionPlan;
use App\Domain\Analytics\Models\{AnalyticsInsight, PredictionRecord};
use App\Domain\Assistant\Models\ConversationMessage;
use App\Domain\Users\Models\User;

class AiDashboardService
{
    public function __construct(private readonly CompletionAccess $access) {}

    public function build(User $user): array
    {
        $householdId = $this->access->householdId($user);

        $recommendations = AnalyticsInsight::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->limit(12)
            ->get()
            ->map(fn (AnalyticsInsight $insight) => [
                'id' => $insight->id,
                'title' => $insight->title,
                'message' => $insight->message,
                'severity' => $insight->severity,
                'confidence' => null,
                'evidence_refs' => $insight->evidence_refs ?? [],
                'model_version' => $insight->model_version,
                'feature_version' => $insight->feature_version,
                'generated_at' => $insight->generated_at?->toIso8601String(),
            ])->values()->all();

        $predictions = PredictionRecord::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->get()
            ->unique('prediction_type')
            ->values()
            ->map(fn (PredictionRecord $prediction) => [
                'id' => $prediction->id,
                'type' => $prediction->prediction_type,
                'method' => $prediction->method,
                'confidence' => $prediction->confidence,
                'explanation' => $prediction->explanation,
                'evidence_refs' => $prediction->evidence_refs ?? [],
                'model_version' => $prediction->model_version,
                'feature_version' => $prediction->feature_version,
                'prediction' => $prediction->prediction,
                'generated_at' => $prediction->generated_at?->toIso8601String(),
            ])->all();

        $plans = ActionPlan::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(function (ActionPlan $plan) {
                $status = is_object($plan->status) ? $plan->status->value : (string) $plan->status;
                $risk = is_object($plan->overall_risk) ? $plan->overall_risk->value : (string) $plan->overall_risk;
                return [
                    'id' => $plan->id,
                    'request' => $plan->request,
                    'origin' => $plan->origin,
                    'status' => $status,
                    'overall_risk' => $risk,
                    'evidence_refs' => $plan->evidence_refs ?? [],
                    'approved_at' => $plan->approved_at?->toIso8601String(),
                    'executed_at' => $plan->executed_at?->toIso8601String(),
                    'approval_state' => match ($status) {
                        'approved' => 'approved',
                        'completed' => 'executed',
                        'cancelled' => 'cancelled',
                        'failed' => 'failed',
                        default => 'pending',
                    },
                ];
            })->values()->all();

        $grounded = ConversationMessage::query()
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->whereHas('session', fn ($query) => $query->where('household_id', $householdId))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ConversationMessage $message) => [
                'id' => $message->id,
                'content' => $message->content,
                'grounding_status' => $message->grounding_status,
                'citations' => $message->citations ?? [],
                'model_provider' => $message->model_provider,
                'model_name' => $message->model_name,
                'model_version' => $message->model_version,
                'response_latency_ms' => data_get($message->metadata, 'total_response_latency_ms'),
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values()->all();

        return [
            'recommendations' => $recommendations,
            'predictions' => $predictions,
            'pending_actions' => collect($plans)->where('approval_state', 'pending')->values()->all(),
            'action_plans' => $plans,
            'recent_grounded_answers' => $grounded,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
