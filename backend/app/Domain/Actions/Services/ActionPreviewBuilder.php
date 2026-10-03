<?php

namespace App\Domain\Actions\Services;

use App\Domain\Actions\Enums\ActionType;

class ActionPreviewBuilder
{
    public function build(ActionType $type, array $payload): array
    {
        return match ($type) {
            ActionType::CreateTask => [
                'effect' => 'Create an internal task',
                'title' => $payload['title'] ?? null,
                'due_at' => $payload['due_at'] ?? null,
                'priority' => $payload['priority'] ?? 'medium',
            ],
            ActionType::UpdateTask => [
                'effect' => 'Modify an existing internal task',
                'task_id' => $payload['task_id'] ?? null,
                'changes' => $payload['changes'] ?? [],
            ],
            ActionType::CreateReminder => [
                'effect' => 'Create an internal reminder',
                'task_id' => $payload['task_id'] ?? null,
                'remind_at' => $payload['remind_at'] ?? null,
            ],
            ActionType::CreateCalendarEvent => [
                'effect' => 'Create an internal LifePilot calendar event',
                'title' => $payload['title'] ?? null,
                'starts_at' => $payload['starts_at'] ?? null,
                'ends_at' => $payload['ends_at'] ?? null,
            ],
            ActionType::PrepareEmailDraft => [
                'effect' => 'Prepare an email draft only; no email will be sent',
                'to' => $payload['to'] ?? null,
                'subject' => $payload['subject'] ?? null,
            ],
            ActionType::CategorizeExpense => [
                'effect' => 'Update internal expense metadata',
                'expense_id' => $payload['expense_id'] ?? null,
                'expense_category_id' => $payload['expense_category_id'] ?? null,
            ],
            ActionType::CreateExpense => [
                'effect' => 'Create an internal expense record only; no payment will occur',
                'title' => $payload['title'] ?? null,
                'amount' => $payload['amount'] ?? null,
                'currency' => $payload['currency'] ?? null,
            ],
            ActionType::CreateAsset => [
                'effect' => 'Create an internal asset record',
                'name' => $payload['name'] ?? null,
            ],
            ActionType::CreateSubscription => [
                'effect' => 'Create an internal subscription record only; no external signup will occur',
                'name' => $payload['name'] ?? null,
                'price' => $payload['price'] ?? null,
                'currency' => $payload['currency'] ?? null,
            ],
            ActionType::ArchiveDocument => [
                'effect' => 'Soft-archive a LifePilot document',
                'document_id' => $payload['document_id'] ?? null,
            ],
            ActionType::RequestIntegrationAction => [
                'effect' => 'Prepare an external integration request for explicit review; LifePilot will not perform financial transactions',
                'integration' => $payload['integration'] ?? null,
                'operation' => $payload['operation'] ?? null,
            ],
        };
    }
}
