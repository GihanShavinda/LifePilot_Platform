<?php

namespace App\Domain\Completion\Services;

use App\Domain\Actions\Models\ActionExecution;
use App\Domain\Assistant\Models\ConversationMessage;
use App\Domain\Completion\Models\{EvaluationCase, EvaluationMetricResult, EvaluationRun};
use App\Domain\Scheduling\Models\NotificationDelivery;
use App\Domain\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EvaluationService
{
    public const VARIANTS = [
        'rules_manual' => 'Rules / manual only',
        'rules_extraction' => 'Rules + document extraction',
        'rules_rag' => 'Rules + RAG',
        'full_agentic' => 'Full LifePilot + agentic action planning',
    ];

    private const METRICS = [
        'extraction_accuracy' => ['Extraction Accuracy', 'rate'],
        'field_precision' => ['Field Precision', 'precision'],
        'field_recall' => ['Field Recall', 'recall'],
        'document_classification_accuracy' => ['Document Classification Accuracy', 'rate'],
        'ocr_success_rate' => ['OCR Success Rate', 'rate'],
        'task_suggestion_precision' => ['Task Suggestion Precision', 'precision'],
        'deadline_extraction_accuracy' => ['Deadline Extraction Accuracy', 'rate'],
        'retrieval_precision_at_k' => ['Retrieval Precision@K', 'precision'],
        'retrieval_recall_at_k' => ['Retrieval Recall@K', 'recall'],
        'citation_accuracy' => ['Citation Accuracy', 'rate'],
        'hallucination_rate' => ['Hallucination Rate', 'failure_rate'],
        'action_approval_accuracy' => ['Action Approval Accuracy', 'rate'],
        'action_execution_success_rate' => ['Action Execution Success Rate', 'operational_execution'],
        'notification_delivery_success' => ['Notification Delivery Success', 'operational_notification'],
        'ai_response_latency' => ['AI Response Latency', 'operational_latency'],
    ];

    public function __construct(
        private readonly CompletionAccess $access,
        private readonly EvaluationMath $math,
        private readonly SecurityReviewService $security,
    ) {}

    public function summary(User $user): array
    {
        $householdId = $this->access->householdId($user);
        $latest = EvaluationRun::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->with('metrics')
            ->latest('generated_at')
            ->first();

        return [
            'latest_run' => $latest ? $this->serializeRun($latest) : null,
            'comparison' => $this->comparison($householdId, $user->id),
            'metric_catalog' => collect(self::METRICS)->map(fn ($config, $key) => [
                'metric_key' => $key,
                'label' => $config[0],
            ])->values()->all(),
            'security_review' => $this->security->review($user),
        ];
    }

    public function run(User $user, string $label = 'P12 final evaluation'): EvaluationRun
    {
        $householdId = $this->access->householdId($user);

        return DB::transaction(function () use ($user, $householdId, $label) {
            $run = EvaluationRun::create([
                'household_id' => $householdId,
                'user_id' => $user->id,
                'label' => $label,
                'status' => 'running',
                'generated_at' => now(),
            ]);

            $resultSummary = [];
            foreach (self::VARIANTS as $variant => $variantLabel) {
                foreach (self::METRICS as $metricKey => [$metricLabel, $kind]) {
                    $metric = $this->measure($householdId, $user->id, $variant, $metricKey, $metricLabel, $kind);
                    $run->metrics()->create($metric);
                    $resultSummary[$variant][$metricKey] = [
                        'value' => $metric['value'],
                        'status' => $metric['status'],
                        'sample_size' => $metric['sample_size'],
                    ];
                }
            }

            $run->update([
                'status' => 'completed',
                'summary' => [
                    'variants' => self::VARIANTS,
                    'results' => $resultSummary,
                    'important_note' => 'Metrics without labeled evaluation cases are explicitly reported as not_measured. LifePilot does not manufacture benchmark scores.',
                ],
                'generated_at' => now(),
            ]);

            return $run->fresh('metrics');
        });
    }

    private function measure(int $householdId, int $userId, string $variant, string $metricKey, string $label, string $kind): array
    {
        $cases = EvaluationCase::query()
            ->where('household_id', $householdId)
            ->where('variant', $variant)
            ->where('metric_key', $metricKey)
            ->get();

        if ($cases->isNotEmpty()) {
            return $this->fromCases($variant, $metricKey, $label, $kind, $cases);
        }

        if ($variant === 'full_agentic' && $kind === 'operational_execution') {
            $query = ActionExecution::query()
                ->where('user_id', $userId)
                ->whereHas('step.plan', fn ($q) => $q->where('household_id', $householdId));
            $total = (clone $query)->count();
            $success = (clone $query)->where('status', 'completed')->count();
            $value = $this->math->rate($success, $total);

            return $this->metricPayload($variant, $metricKey, $label, $value, '%', $value === null ? 'not_measured' : 'measured_operationally', $total,
                $value === null ? 'No action execution samples exist yet.' : 'Operational success rate from persisted P9 action executions.');
        }

        if ($variant === 'full_agentic' && $kind === 'operational_notification') {
            $query = NotificationDelivery::query()
                ->whereHas('notification', fn ($q) => $q->where('household_id', $householdId)->where('user_id', $userId));
            $total = (clone $query)->count();
            $success = (clone $query)->where('status', 'delivered')->count();
            $value = $this->math->rate($success, $total);

            return $this->metricPayload($variant, $metricKey, $label, $value, '%', $value === null ? 'not_measured' : 'measured_operationally', $total,
                $value === null ? 'No notification-delivery samples exist yet.' : 'Operational delivery rate from persisted P6 notification deliveries.');
        }

        if ($variant === 'full_agentic' && $kind === 'operational_latency') {
            $messages = ConversationMessage::query()
                ->where('user_id', $userId)
                ->where('role', 'assistant')
                ->whereHas('session', fn ($q) => $q->where('household_id', $householdId))
                ->get();
            $latencies = $messages->map(fn (ConversationMessage $message) => data_get($message->metadata, 'total_response_latency_ms'))->filter(fn ($value) => is_numeric($value))->values()->all();
            $value = $this->math->mean($latencies);

            return $this->metricPayload($variant, $metricKey, $label, $value, 'ms', $value === null ? 'not_measured' : 'measured_operationally', count($latencies),
                $value === null ? 'No instrumented P12 assistant-response latency samples exist yet.' : 'Mean end-to-end response latency from P12-instrumented assistant messages.');
        }

        return $this->metricPayload(
            $variant,
            $metricKey,
            $label,
            null,
            $metricKey === 'ai_response_latency' ? 'ms' : '%',
            'not_measured',
            0,
            'A labeled evaluation dataset is required for this metric. No score is fabricated when ground truth is unavailable.'
        );
    }

    private function fromCases(string $variant, string $metricKey, string $label, string $kind, Collection $cases): array
    {
        $tp = $cases->where('outcome', 'tp')->count();
        $fp = $cases->where('outcome', 'fp')->count();
        $fn = $cases->where('outcome', 'fn')->count();
        $pass = $cases->whereIn('outcome', ['pass', 'tp', 'tn'])->count();
        $fail = $cases->whereIn('outcome', ['fail', 'fp', 'fn'])->count();

        $value = match ($kind) {
            'precision' => $this->math->precision($tp, $fp),
            'recall' => $this->math->recall($tp, $fn),
            'failure_rate' => $this->math->rate($fail, $pass + $fail),
            default => $this->math->rate($pass, $pass + $fail),
        };

        return $this->metricPayload(
            $variant,
            $metricKey,
            $label,
            $value,
            '%',
            $value === null ? 'not_measured' : 'measured_labeled',
            $cases->count(),
            $value === null ? 'The labeled cases do not contain enough positive/negative outcomes for this metric.' : 'Calculated from persisted labeled P12 evaluation cases.'
        );
    }

    private function metricPayload(string $variant, string $metricKey, string $label, ?float $value, string $unit, string $status, int $sampleSize, string $explanation): array
    {
        return [
            'variant' => $variant,
            'metric_key' => $metricKey,
            'label' => $label,
            'value' => $value,
            'unit' => $unit,
            'status' => $status,
            'sample_size' => $sampleSize,
            'explanation' => $explanation,
            'metadata' => ['variant_label' => self::VARIANTS[$variant] ?? $variant],
        ];
    }

    private function comparison(int $householdId, int $userId): array
    {
        $latest = EvaluationRun::query()
            ->where('household_id', $householdId)
            ->where('user_id', $userId)
            ->with('metrics')
            ->latest('generated_at')
            ->first();

        $capabilities = [
            'rules_manual' => ['manual_crud', 'deterministic_rules', 'audit'],
            'rules_extraction' => ['manual_crud', 'deterministic_rules', 'document_extraction', 'human_review', 'audit'],
            'rules_rag' => ['manual_crud', 'document_extraction', 'semantic_search', 'grounded_rag', 'citations', 'audit'],
            'full_agentic' => ['manual_crud', 'document_extraction', 'grounded_rag', 'citations', 'safe_action_plans', 'household_privacy', 'analytics', 'audit'],
        ];

        return collect(self::VARIANTS)->map(function ($label, $key) use ($capabilities, $latest) {
            $metrics = $latest
                ? $latest->metrics->where('variant', $key)->values()->map(fn (EvaluationMetricResult $metric) => $this->serializeMetric($metric))->all()
                : [];

            return [
                'variant' => $key,
                'label' => $label,
                'capabilities' => $capabilities[$key],
                'metrics' => $metrics,
            ];
        })->values()->all();
    }

    public function serializeRun(EvaluationRun $run): array
    {
        return [
            'id' => $run->id,
            'label' => $run->label,
            'status' => $run->status,
            'summary' => $run->summary,
            'generated_at' => $run->generated_at?->toIso8601String(),
            'metrics' => $run->relationLoaded('metrics') ? $run->metrics->map(fn (EvaluationMetricResult $metric) => $this->serializeMetric($metric))->values()->all() : [],
        ];
    }

    private function serializeMetric(EvaluationMetricResult $metric): array
    {
        return [
            'variant' => $metric->variant,
            'metric_key' => $metric->metric_key,
            'label' => $metric->label,
            'value' => $metric->value,
            'unit' => $metric->unit,
            'status' => $metric->status,
            'sample_size' => $metric->sample_size,
            'explanation' => $metric->explanation,
        ];
    }
}
