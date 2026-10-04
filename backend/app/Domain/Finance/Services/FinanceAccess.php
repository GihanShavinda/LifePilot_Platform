<?php

namespace App\Domain\Finance\Services;

use App\Domain\Collaboration\Services\{HouseholdAccessService, SharedResourceService};
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\User;
use Illuminate\Http\Request;

class FinanceAccess
{
    public function __construct(
        private HouseholdAccessService $households,
        private SharedResourceService $sharing,
    ) {
    }

    public function household(Request $request, bool $write = false): int
    {
        $id = $this->households->householdId($request->user());
        if ($write) {
            $this->households->ensureWrite($request->user(), $id);
        }
        return $id;
    }

    public function requireDocument(int $householdId, ?int $documentId, ?User $user = null, bool $write = false): void
    {
        if ($documentId === null) {
            return;
        }

        $doc = Document::query()->where('household_id', $householdId)->findOrFail($documentId);
        if ($user) {
            $write ? $this->sharing->ensureWrite($user, $doc) : $this->sharing->ensureRead($user, $doc);
        }
    }

    public function ensureResource(User $user, \Illuminate\Database\Eloquent\Model $resource, bool $write = false): void
    {
        $write ? $this->sharing->ensureWrite($user, $resource) : $this->sharing->ensureRead($user, $resource);
    }
}
