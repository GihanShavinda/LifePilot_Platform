<?php
namespace App\Domain\Obligations\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ObligationService
{
    public static function fingerprint(int $householdId, string $type, string $title, ?string $dueDate, ?int $documentId): string
    {
        return hash('sha256', implode('|', [$householdId, mb_strtolower(trim($type)), mb_strtolower(trim($title)), substr((string) $dueDate, 0, 10), $documentId ?? 'manual']));
    }

    public function createManual(User $user, int $householdId, array $data): Obligation
    {
        if (!empty($data['document_id'])) {
            $doc = Document::where('household_id', $householdId)->findOrFail($data['document_id']);
        }
        $due = $data['due_at'] ?? null;
        if ($due && CarbonImmutable::parse($due)->isPast()) throw ValidationException::withMessages(['due_at' => 'Choose a future due date for a new obligation.']);
        $key = self::fingerprint($householdId, $data['type'], $data['title'], $due, $data['document_id'] ?? null);
        if (Obligation::where('household_id', $householdId)->where('dedupe_key', $key)->exists()) throw ValidationException::withMessages(['title' => 'An equivalent obligation already exists.']);
        return Obligation::create(array_merge($data, ['user_id' => $user->id, 'household_id' => $householdId, 'dedupe_key' => $key, 'status' => 'suggested']));
    }

    public function approve(User $user, Obligation $obligation): Task
    {
        return DB::transaction(function () use ($user, $obligation) {
            $obligation = Obligation::whereKey($obligation->id)->lockForUpdate()->firstOrFail();
            if ($obligation->status !== 'suggested') throw ValidationException::withMessages(['obligation' => 'This obligation has already been handled.']);
            if ($obligation->due_at && $obligation->due_at->isPast()) throw ValidationException::withMessages(['due_at' => 'Review the overdue date before creating a task.']);
            $task = Task::create([
                'household_id' => $obligation->household_id, 'user_id' => $user->id,
                'obligation_id' => $obligation->id, 'document_id' => $obligation->document_id,
                'title' => $obligation->title, 'description' => $obligation->description,
                'due_at' => $obligation->due_at, 'priority' => 'medium', 'status' => 'pending',
            ]);
            $obligation->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $user->id]);
            TaskActivityService::record($task, $user->id, 'created_from_approved_obligation', ['obligation_id' => $obligation->id]);
            app(ReminderService::class)->recommend($task);
            return $task;
        });
    }
}
