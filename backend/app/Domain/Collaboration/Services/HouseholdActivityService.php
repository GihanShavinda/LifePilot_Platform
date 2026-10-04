<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Models\HouseholdActivity;
use App\Domain\Scheduling\Models\Notification;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;

class HouseholdActivityService
{
    public function record(
        int $householdId,
        ?User $actor,
        string $eventType,
        string $description,
        ?string $subjectType = null,
        int|string|null $subjectId = null,
        array $metadata = [],
        bool $notifyHousehold = false,
    ): HouseholdActivity {
        $activity = HouseholdActivity::create([
            'household_id' => $householdId,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId !== null ? (string) $subjectId : null,
            'description' => $description,
            'metadata' => $metadata,
        ]);

        if ($notifyHousehold) {
            $this->notifyMembers($householdId, $actor?->id, $eventType, $description, $metadata);
        }

        return $activity;
    }

    public function notifyMembers(int $householdId, ?int $actorId, string $type, string $body, array $payload = []): void
    {
        $members = \App\Domain\Users\Models\HouseholdMember::query()
            ->where('household_id', $householdId)
            ->where('user_id', '!=', $actorId ?? 0)
            ->pluck('user_id');

        foreach ($members as $userId) {
            Notification::firstOrCreate(
                [
                    'user_id' => $userId,
                    'dedupe_key' => 'household:'.$type.':'.sha1($body.'|'.json_encode($payload).'|'.now()->format('YmdHi')),
                ],
                [
                    'household_id' => $householdId,
                    'type' => 'household.'.$type,
                    'title' => 'Household update',
                    'body' => $body,
                    'payload' => $payload,
                    'priority' => 'normal',
                    'status' => 'pending',
                    'scheduled_at' => now(),
                ]
            );
        }
    }
}
