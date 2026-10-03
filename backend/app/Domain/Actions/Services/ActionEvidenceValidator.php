<?php

namespace App\Domain\Actions\Services;

use App\Domain\Assistant\Models\ConversationMessage;
use App\Domain\Documents\Models\{Document, ExtractedField};
use App\Domain\Finance\Models\{Asset, Expense, Subscription, Warranty};
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Scheduling\Models\CalendarEvent;
use Illuminate\Validation\ValidationException;

class ActionEvidenceValidator
{
    public function validateReferences(int $householdId, array $refs): array
    {
        $resolved = [];

        foreach (array_values(array_unique($refs)) as $ref) {
            $resolved[$ref] = $this->resolve($householdId, $ref);
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

    private function resolve(int $householdId, string $ref): array
    {
        [$type, $id] = array_pad(explode(':', $ref, 2), 2, null);
        $id = (int) $id;

        if (!$type || !$id) {
            throw ValidationException::withMessages(['evidence_refs' => "Invalid evidence reference: {$ref}."]);
        }

        $record = match ($type) {
            'document' => Document::where('household_id', $householdId)->find($id),
            'obligation' => Obligation::where('household_id', $householdId)->find($id),
            'task' => Task::where('household_id', $householdId)->find($id),
            'expense' => Expense::where('household_id', $householdId)->find($id),
            'subscription' => Subscription::where('household_id', $householdId)->find($id),
            'asset' => Asset::where('household_id', $householdId)->find($id),
            'warranty' => Warranty::whereHas('asset', fn ($q) => $q->where('household_id', $householdId))->find($id),
            'calendar_event' => CalendarEvent::where('household_id', $householdId)->find($id),
            'extraction_field' => ExtractedField::whereHas('extraction.document', fn ($q) => $q->where('household_id', $householdId))->find($id),
            'conversation_message' => ConversationMessage::whereHas('session', fn ($q) => $q->where('household_id', $householdId))->find($id),
            default => null,
        };

        if (!$record) {
            throw ValidationException::withMessages([
                'evidence_refs' => "Evidence reference is missing or unauthorized: {$ref}.",
            ]);
        }

        return [
            'ref' => $ref,
            'type' => $type,
            'id' => $id,
        ];
    }
}
