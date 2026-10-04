<?php

namespace App\Domain\Actions\Services;

use App\Domain\Assistant\Models\ConversationMessage;
use App\Domain\Collaboration\Services\SharedResourceService;
use App\Domain\Documents\Models\{Document, ExtractedField};
use App\Domain\Finance\Models\{Asset, Expense, Subscription, Warranty};
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;
use Illuminate\Validation\ValidationException;

class ActionEvidenceValidator
{
    public function __construct(private SharedResourceService $sharing)
    {
    }

    public function validateReferences(User $user, int $householdId, array $refs): array
    {
        $resolved = [];
        foreach (array_values(array_unique($refs)) as $ref) {
            $resolved[$ref] = $this->resolve($user, $householdId, $ref);
        }
        return $resolved;
    }

    public function requireEvidenceForAssistantOrigin(string $origin, array $refs): void
    {
        if ($origin === 'assistant' && count($refs) === 0) {
            throw ValidationException::withMessages([
                'evidence_refs' => 'Assistant-generated action plans require grounded evidence.',
            ]);
        }
    }

    private function resolve(User $user, int $householdId, string $ref): array
    {
        [$type, $id] = array_pad(explode(':', $ref, 2), 2, null);
        $id = (int) $id;
        if (!$type || !$id) {
            throw ValidationException::withMessages(['evidence_refs' => "Invalid evidence reference: {$ref}."]);
        }

        $record = match ($type) {
            'document' => Document::accessibleTo($user)->where('household_id', $householdId)->find($id),
            'obligation' => Obligation::where('household_id', $householdId)->where('user_id', $user->id)->find($id),
            'task' => Task::accessibleTo($user)->where('household_id', $householdId)->find($id),
            'expense' => Expense::accessibleTo($user)->where('household_id', $householdId)->find($id),
            'subscription' => Subscription::where('household_id', $householdId)->where('user_id', $user->id)->find($id),
            'asset' => Asset::accessibleTo($user)->where('household_id', $householdId)->find($id),
            'warranty' => Warranty::whereHas('asset', fn ($q) => $q->accessibleTo($user)->where('household_id', $householdId))->find($id),
            'calendar_event' => CalendarEvent::accessibleTo($user)->where('household_id', $householdId)->find($id),
            'extraction_field' => ExtractedField::whereHas('extraction.document', fn ($q) => $q->accessibleTo($user)->where('household_id', $householdId))->find($id),
            'conversation_message' => ConversationMessage::where('user_id', $user->id)->whereHas('session', fn ($q) => $q->where('household_id', $householdId)->where('user_id', $user->id))->find($id),
            default => null,
        };

        if (!$record) {
            throw ValidationException::withMessages([
                'evidence_refs' => "Evidence reference is missing or unauthorized: {$ref}.",
            ]);
        }

        return ['ref' => $ref, 'type' => $type, 'id' => $id];
    }
}
