<?php

namespace App\Domain\Documents\Services;

use App\Domain\Collaboration\Services\{HouseholdAccessService, SharedResourceService};
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\User;

class DocumentAuthorization
{
    public function __construct(
        private HouseholdAccessService $households,
        private SharedResourceService $sharing,
    ) {
    }

    public function householdId(User $user): int
    {
        return $this->households->householdId($user);
    }

    public function ensure(User $user, Document $doc, bool $write = false): void
    {
        $write ? $this->sharing->ensureWrite($user, $doc) : $this->sharing->ensureRead($user, $doc);
    }
}
