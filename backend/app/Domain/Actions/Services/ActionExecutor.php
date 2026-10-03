<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Enums\{ActionPlanStatus, ActionStepStatus, ActionType};
use App\Domain\Actions\Models\{ActionExecution, ActionPlan, ActionResult, ActionStep};
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\{Asset, Expense, ExpenseCategory, Subscription};
use App\Domain\Obligations\Models\{Reminder, Task};
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActionExecutor
{
    public function execute(User $user, ActionPlan $plan): ActionPlan
    {
        if ($plan->status === ActionPlanStatus::Completed) {
            return $plan->fresh(['steps', 'executions.result']);
        }

        if (!in_array($plan->status, [ActionPlanStatus::Approved, ActionPlanStatus::PartiallyApproved], true)) {
            throw ValidationException::withMessages(['plan' => 'The action plan must be approved before execution.']);
        }

        $steps = $plan->steps()
            ->where('status', ActionStepStatus::Approved->value)
            ->orderBy('sequence')
            ->get();

        if ($steps->isEmpty()) {
            throw ValidationException::withMessages(['plan' => 'There are no approved steps to execute.']);
        }

        $executions = [];
        foreach ($steps as $step) {
            $executionKey = hash('sha256', $plan->id . '|execute|' . $step->idempotency_key);
            $execution = ActionExecution::firstOrCreate(
                ['idempotency_key' => $executionKey],
                [
                    'action_plan_id' => $plan->id,
                    'action_step_id' => $step->id,
                    'user_id' => $user->id,
                    'status' => 'queued',
                    'attempt' => 1,
                ]
            );

            if ($execution->status === 'completed') {
                continue;
            }

            $executions[$step->id] = $execution;
        }

        if (!$executions) {
            $plan->update(['status' => ActionPlanStatus::Completed, 'executed_at' => $plan->executed_at ?? now()]);
            return $plan->fresh(['steps', 'executions.result']);
        }

        $plan->update(['status' => ActionPlanStatus::Executing]);
        $completedInAttempt = [];
        $currentStep = null;

        try {
            DB::transaction(function () use ($user, $plan, $steps, $executions, &$completedInAttempt, &$currentStep) {
                foreach ($steps as $step) {
                    if (!isset($executions[$step->id])) {
                        continue;
                    }

                    $currentStep = $step;
                    $execution = $executions[$step->id];
                    $execution->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
                    $step->update(['status' => ActionStepStatus::Executing]);

                    $outcome = $this->executeStep($user, $plan, $step);
                    $verification = $this->verify($plan, $step, $outcome);

                    if (!($verification['verified'] ?? false)) {
                        throw new \RuntimeException('Action verification failed for step ' . $step->id . '.');
                    }

                    ActionResult::updateOrCreate(
                        ['action_execution_id' => $execution->id],
                        [
                            'action_step_id' => $step->id,
                            'status' => 'completed',
                            'entity_type' => $outcome['entity_type'] ?? null,
                            'entity_id' => $outcome['entity_id'] ?? null,
                            'result' => $outcome['result'] ?? [],
                            'verification' => $verification,
                            'rollback_status' => null,
                            'rollback_result' => null,
                        ]
                    );

                    $execution->update(['status' => 'completed', 'finished_at' => now()]);
                    $step->update(['status' => ActionStepStatus::Completed]);
                    $completedInAttempt[] = $step->id;
                }
            });

            $plan->update(['status' => ActionPlanStatus::Completed, 'executed_at' => now()]);
        } catch (\Throwable $e) {
            foreach ($completedInAttempt as $stepId) {
                $execution = $executions[$stepId] ?? null;
                if (!$execution) {
                    continue;
                }
                $execution->update([
                    'status' => 'rolled_back',
                    'finished_at' => now(),
                    'error' => 'Rolled back because a later step failed: ' . $e->getMessage(),
                ]);
                ActionResult::updateOrCreate(
                    ['action_execution_id' => $execution->id],
                    [
                        'action_step_id' => $stepId,
                        'status' => 'rolled_back',
                        'rollback_status' => 'database_transaction',
                        'rollback_result' => ['rolled_back' => true],
                        'verification' => ['verified' => false, 'reason' => 'transaction_rolled_back'],
                    ]
                );
                ActionStep::whereKey($stepId)->update(['status' => ActionStepStatus::RolledBack->value]);
            }

            if ($currentStep && isset($executions[$currentStep->id])) {
                $execution = $executions[$currentStep->id];
                $execution->update(['status' => 'failed', 'finished_at' => now(), 'error' => $e->getMessage()]);
                ActionResult::updateOrCreate(
                    ['action_execution_id' => $execution->id],
                    [
                        'action_step_id' => $currentStep->id,
                        'status' => 'failed',
                        'result' => ['error' => $e->getMessage()],
                        'verification' => ['verified' => false],
                        'rollback_status' => 'database_transaction',
                        'rollback_result' => ['rolled_back' => true],
                    ]
                );
                $currentStep->update(['status' => ActionStepStatus::Failed]);
            }

            $plan->update([
                'status' => ActionPlanStatus::Failed,
                'metadata' => array_merge($plan->metadata ?? [], ['last_execution_error' => $e->getMessage()]),
            ]);
        }

        return $plan->fresh(['steps.approvals', 'executions.result']);
    }

    private function executeStep(User $user, ActionPlan $plan, ActionStep $step): array
    {
        $payload = $step->payload ?? [];
        $householdId = (int) $plan->household_id;

        return match ($step->action_type) {
            ActionType::CreateTask => $this->createTask($user, $householdId, $payload),
            ActionType::UpdateTask => $this->updateTask($householdId, $payload),
            ActionType::CreateReminder => $this->createReminder($user, $householdId, $payload),
            ActionType::CreateCalendarEvent => $this->createCalendarEvent($user, $householdId, $payload),
            ActionType::PrepareEmailDraft => $this->prepareEmailDraft($payload),
            ActionType::CategorizeExpense => $this->categorizeExpense($householdId, $payload),
            ActionType::CreateExpense => $this->createExpense($user, $householdId, $payload),
            ActionType::CreateAsset => $this->createAsset($user, $householdId, $payload),
            ActionType::CreateSubscription => $this->createSubscription($user, $householdId, $payload),
            ActionType::ArchiveDocument => $this->archiveDocument($householdId, $payload),
            ActionType::RequestIntegrationAction => $this->prepareIntegrationRequest($payload),
        };
    }

    private function createTask(User $user, int $householdId, array $p): array
    {
        $task = Task::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
            'obligation_id' => $p['obligation_id'] ?? null,
            'document_id' => $p['document_id'] ?? null,
            'title' => $this->required($p, 'title'),
            'description' => $p['description'] ?? null,
            'priority' => $p['priority'] ?? 'medium',
            'status' => 'pending',
            'labels' => $p['labels'] ?? null,
            'due_at' => $p['due_at'] ?? null,
        ]);
        return $this->modelOutcome('task', $task);
    }

    private function updateTask(int $householdId, array $p): array
    {
        $task = Task::where('household_id', $householdId)->findOrFail((int) $this->required($p, 'task_id'));
        $allowed = array_intersect_key($p['changes'] ?? [], array_flip(['title', 'description', 'priority', 'status', 'labels', 'due_at']));
        if (!$allowed) {
            throw new \InvalidArgumentException('No supported task changes were provided.');
        }
        $task->update($allowed);
        return $this->modelOutcome('task', $task->fresh());
    }

    private function createReminder(User $user, int $householdId, array $p): array
    {
        $task = Task::where('household_id', $householdId)->findOrFail((int) $this->required($p, 'task_id'));
        $reminder = Reminder::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'remind_at' => $this->required($p, 'remind_at'),
            'channel' => $p['channel'] ?? 'in_app',
            'status' => 'scheduled',
            'recommended' => false,
        ]);
        return $this->modelOutcome('reminder', $reminder);
    }

    private function createCalendarEvent(User $user, int $householdId, array $p): array
    {
        if (!empty($p['calendar_connection_id']) || !empty($p['external_event_id'])) {
            throw new \RuntimeException('External calendar modification must use a high-risk integration request.');
        }
        $event = CalendarEvent::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
            'task_id' => $p['task_id'] ?? null,
            'document_id' => $p['document_id'] ?? null,
            'title' => $this->required($p, 'title'),
            'description' => $p['description'] ?? null,
            'location' => $p['location'] ?? null,
            'starts_at' => $this->required($p, 'starts_at'),
            'ends_at' => $p['ends_at'] ?? $p['starts_at'],
            'timezone' => $p['timezone'] ?? 'UTC',
            'status' => 'confirmed',
            'reminder_offsets' => $p['reminder_offsets'] ?? null,
        ]);
        return $this->modelOutcome('calendar_event', $event);
    }

    private function prepareEmailDraft(array $p): array
    {
        return [
            'entity_type' => 'email_draft',
            'entity_id' => null,
            'result' => [
                'to' => $p['to'] ?? null,
                'subject' => $this->required($p, 'subject'),
                'body' => $this->required($p, 'body'),
                'sent' => false,
            ],
        ];
    }

    private function categorizeExpense(int $householdId, array $p): array
    {
        $expense = Expense::where('household_id', $householdId)->findOrFail((int) $this->required($p, 'expense_id'));
        $category = ExpenseCategory::where('household_id', $householdId)->findOrFail((int) $this->required($p, 'expense_category_id'));
        $expense->update(['expense_category_id' => $category->id]);
        return $this->modelOutcome('expense', $expense->fresh());
    }

    private function createExpense(User $user, int $householdId, array $p): array
    {
        $expense = Expense::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
            'document_id' => $p['document_id'] ?? null,
            'title' => $this->required($p, 'title'),
            'description' => $p['description'] ?? null,
            'amount' => $this->required($p, 'amount'),
            'currency' => strtoupper($this->required($p, 'currency')),
            'expense_date' => $p['expense_date'] ?? today()->toDateString(),
            'source' => 'agentic_approved',
        ]);
        return $this->modelOutcome('expense', $expense);
    }

    private function createAsset(User $user, int $householdId, array $p): array
    {
        $asset = Asset::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
            'document_id' => $p['document_id'] ?? null,
            'name' => $this->required($p, 'name'),
            'brand' => $p['brand'] ?? null,
            'model' => $p['model'] ?? null,
            'serial_number' => $p['serial_number'] ?? null,
            'purchase_date' => $p['purchase_date'] ?? null,
            'purchase_price' => $p['purchase_price'] ?? null,
            'currency' => isset($p['currency']) ? strtoupper((string) $p['currency']) : null,
            'location' => $p['location'] ?? null,
            'status' => 'owned',
        ]);
        return $this->modelOutcome('asset', $asset);
    }

    private function createSubscription(User $user, int $householdId, array $p): array
    {
        $dedupe = hash('sha256', strtolower(($p['provider'] ?? '') . '|' . ($p['name'] ?? '') . '|' . ($p['next_billing_date'] ?? '')));
        $subscription = Subscription::create([
            'household_id' => $householdId,
            'user_id' => $user->id,
            'document_id' => $p['document_id'] ?? null,
            'name' => $this->required($p, 'name'),
            'provider' => $this->required($p, 'provider'),
            'price' => $this->required($p, 'price'),
            'currency' => strtoupper($this->required($p, 'currency')),
            'billing_cycle' => $p['billing_cycle'] ?? 'monthly',
            'next_billing_date' => $this->required($p, 'next_billing_date'),
            'renewal_type' => $p['renewal_type'] ?? 'automatic',
            'cancellation_deadline' => $p['cancellation_deadline'] ?? null,
            'status' => 'active',
            'dedupe_key' => $dedupe,
        ]);
        return $this->modelOutcome('subscription', $subscription);
    }

    private function archiveDocument(int $householdId, array $p): array
    {
        $document = Document::where('household_id', $householdId)->findOrFail((int) $this->required($p, 'document_id'));
        $document->update(['archived_at' => now()]);
        return $this->modelOutcome('document', $document->fresh());
    }

    private function prepareIntegrationRequest(array $p): array
    {
        return [
            'entity_type' => 'integration_request',
            'entity_id' => null,
            'result' => [
                'integration' => $this->required($p, 'integration'),
                'operation' => $this->required($p, 'operation'),
                'parameters' => $p['parameters'] ?? [],
                'external_execution_performed' => false,
                'manual_or_connector_execution_required' => true,
            ],
        ];
    }

    private function verify(ActionPlan $plan, ActionStep $step, array $outcome): array
    {
        if (($outcome['entity_id'] ?? null) !== null) {
            return ['verified' => true, 'method' => 'persisted_entity_id', 'entity_id' => $outcome['entity_id']];
        }

        if (in_array($step->action_type, [ActionType::PrepareEmailDraft, ActionType::RequestIntegrationAction], true)) {
            return ['verified' => true, 'method' => 'prepared_non_executing_result'];
        }

        return ['verified' => false, 'method' => 'unknown'];
    }

    private function modelOutcome(string $type, Model $model): array
    {
        return [
            'entity_type' => $type,
            'entity_id' => $model->getKey(),
            'result' => ['id' => $model->getKey(), 'type' => $type],
        ];
    }

    private function required(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException("Missing required payload field: {$key}.");
        }
        return (string) $value;
    }
}
