<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Collaboration\Services\{HouseholdAccessService, SharedResourceService};
use App\Domain\Documents\Models\Document;
use App\Domain\Obligations\Models\Task;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;

class CalendarAccess
{
    public function __construct(
        private HouseholdAccessService $households,
        private SharedResourceService $sharing,
    ) {
    }

    public function household(User $user, bool $write = false, ?int $requested = null): int
    {
        $membership = $this->households->membership($user, $requested);
        if ($write) {
            $this->households->ensureWrite($user, (int) $membership->household_id);
        }
        return (int) $membership->household_id;
    }

    public function event(User $user, int $id, bool $write = false): CalendarEvent
    {
        $event = CalendarEvent::accessibleTo($user)->findOrFail($id);
        $write ? $this->sharing->ensureWrite($user, $event) : $this->sharing->ensureRead($user, $event);
        return $event;
    }

    public function document(User $user, int $id, int $household, bool $write = false): Document
    {
        $doc = Document::accessibleTo($user)->where('household_id', $household)->findOrFail($id);
        $write ? $this->sharing->ensureWrite($user, $doc) : $this->sharing->ensureRead($user, $doc);
        return $doc;
    }

    public function task(User $user, int $id, int $household, bool $write = false): Task
    {
        $task = Task::accessibleTo($user)->where('household_id', $household)->findOrFail($id);
        $write ? $this->sharing->ensureWrite($user, $task) : $this->sharing->ensureRead($user, $task);
        return $task;
    }
}
