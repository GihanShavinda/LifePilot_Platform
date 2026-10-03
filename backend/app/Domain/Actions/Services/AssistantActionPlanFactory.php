<?php

namespace App\Domain\Actions\Services;

use App\Domain\Assistant\Models\ConversationMessage;
use App\Domain\Users\Models\User;
use Illuminate\Validation\ValidationException;

class AssistantActionPlanFactory
{
    public function __construct(
        private readonly ActionAccess $access,
        private readonly ActionPlanService $plans,
    ) {
    }

    public function fromMessage(User $user, int $messageId): \App\Domain\Actions\Models\ActionPlan
    {
        $householdId = $this->access->householdId($user);

        $message = ConversationMessage::query()
            ->whereKey($messageId)
            ->where('user_id', $user->id)
            ->where('role', 'assistant')
            ->whereHas('session', fn ($q) => $q->where('household_id', $householdId))
            ->firstOrFail();

        $draft = $message->metadata['draft'] ?? null;
        if (!is_array($draft)) {
            throw ValidationException::withMessages([
                'message' => 'This assistant response does not contain an actionable draft.',
            ]);
        }

        $citations = array_values(array_unique(array_merge(
            $message->citations ?? [],
            $draft['citations'] ?? []
        )));

        $type = $draft['type'] ?? null;
        $step = match ($type) {
            'task' => [
                'action_type' => 'create_task',
                'title' => 'Create task from assistant draft',
                'payload' => [
                    'title' => $draft['title'] ?? 'LifePilot task',
                    'description' => $draft['description'] ?? 'Created from an approved grounded assistant draft.',
                    'due_at' => $draft['due_at'] ?? null,
                    'priority' => $draft['priority'] ?? 'medium',
                ],
                'evidence_refs' => $citations,
            ],
            'event' => [
                'action_type' => 'create_calendar_event',
                'title' => 'Create event from assistant draft',
                'payload' => [
                    'title' => $draft['title'] ?? 'LifePilot event',
                    'description' => $draft['description'] ?? 'Created from an approved grounded assistant draft.',
                    'starts_at' => $draft['starts_at'] ?? null,
                    'ends_at' => $draft['ends_at'] ?? ($draft['starts_at'] ?? null),
                    'timezone' => $draft['timezone'] ?? 'UTC',
                ],
                'evidence_refs' => $citations,
            ],
            default => throw ValidationException::withMessages([
                'message' => 'The assistant draft type is not supported by P9.',
            ]),
        };

        return $this->plans->create($user, [
            'request' => 'Convert assistant message #' . $message->id . ' into a safe action plan.',
            'origin' => 'assistant',
            'source_conversation_message_id' => $message->id,
            'evidence_refs' => $citations,
            'steps' => [$step],
            'metadata' => ['source' => 'p8_grounded_assistant'],
            'idempotency_key' => hash('sha256', 'assistant-message:' . $message->id),
        ]);
    }
}
