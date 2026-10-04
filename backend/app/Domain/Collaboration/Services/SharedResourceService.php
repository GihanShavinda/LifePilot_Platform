<?php

namespace App\Domain\Collaboration\Services;

use App\Domain\Collaboration\Enums\SharingScope;
use App\Domain\Collaboration\Models\SharedResource;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\{Asset, Expense};
use App\Domain\Obligations\Models\Task;
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SharedResourceService
{
    public const RESOURCE_MODELS = [
        'document' => Document::class,
        'task' => Task::class,
        'expense' => Expense::class,
        'asset' => Asset::class,
        'calendar_event' => CalendarEvent::class,
    ];

    public function __construct(private HouseholdAccessService $households)
    {
    }

    public function resolve(string $type, int $id): Model
    {
        $class = self::RESOURCE_MODELS[$type] ?? null;
        if (!$class) {
            throw ValidationException::withMessages(['resource_type' => 'Unsupported shareable resource type.']);
        }

        return $class::query()->findOrFail($id);
    }

    public function scope(Model $resource): SharingScope
    {
        $type = $this->typeForModel($resource);
        $row = SharedResource::query()
            ->where('household_id', $resource->household_id)
            ->where('resource_type', $type)
            ->where('resource_id', $resource->getKey())
            ->first();

        return $row?->scope ?? SharingScope::Private;
    }

    public function canRead(User $user, Model $resource): bool
    {
        try {
            $this->households->membership($user, (int) $resource->household_id);
        } catch (AuthorizationException) {
            return false;
        }

        if ((int) $resource->user_id === (int) $user->id) {
            return true;
        }

        return $this->scope($resource) === SharingScope::Household;
    }

    public function canWrite(User $user, Model $resource): bool
    {
        if (!$this->canRead($user, $resource)) {
            return false;
        }

        if ((int) $resource->user_id === (int) $user->id) {
            return $this->households->canWrite($user, (int) $resource->household_id);
        }

        return $this->scope($resource) === SharingScope::Household
            && $this->households->canWrite($user, (int) $resource->household_id);
    }

    public function ensureRead(User $user, Model $resource): void
    {
        if (!$this->canRead($user, $resource)) {
            throw new AuthorizationException('This resource is private or outside your household access.');
        }
    }

    public function ensureWrite(User $user, Model $resource): void
    {
        if (!$this->canWrite($user, $resource)) {
            throw new AuthorizationException('You cannot modify this resource.');
        }
    }

    public function share(User $actor, Model $resource, SharingScope $scope): SharedResource
    {
        $this->households->membership($actor, (int) $resource->household_id);
        $role = $this->households->normalizedRole($this->households->role($actor, (int) $resource->household_id));
        $owns = (int) $resource->user_id === (int) $actor->id;
        $canAdministrate = in_array($role, ['owner', 'admin'], true);

        if (!$owns && !$canAdministrate) {
            throw new AuthorizationException('Only the resource owner or a household owner/admin may change sharing.');
        }

        return SharedResource::updateOrCreate(
            [
                'household_id' => $resource->household_id,
                'resource_type' => $this->typeForModel($resource),
                'resource_id' => $resource->getKey(),
            ],
            [
                'owner_user_id' => $resource->user_id,
                'shared_by_user_id' => $actor->id,
                'scope' => $scope,
                'shared_at' => $scope === SharingScope::Household ? now() : null,
            ]
        );
    }

    public function typeForModel(Model $resource): string
    {
        foreach (self::RESOURCE_MODELS as $type => $class) {
            if ($resource instanceof $class) {
                return $type;
            }
        }

        throw ValidationException::withMessages(['resource_type' => 'Unsupported shareable model.']);
    }

    public function canAccessSource(User $user, array $source): bool
    {
        $type = $source['type'] ?? $source['record'] ?? null;
        $id = $source['id'] ?? null;

        if (($source['document_id'] ?? null) !== null) {
            $document = Document::query()->find((int) $source['document_id']);
            return $document ? $this->canRead($user, $document) : false;
        }

        $map = [
            'document' => 'document', 'documents' => 'document', 'document_chunk' => 'document',
            'task' => 'task', 'tasks' => 'task',
            'expense' => 'expense', 'expenses' => 'expense',
            'asset' => 'asset', 'assets' => 'asset',
            'calendar_event' => 'calendar_event', 'calendar_events' => 'calendar_event',
        ];

        $resourceType = $map[$type] ?? null;
        if ($resourceType && $id) {
            $resource = $this->resolve($resourceType, (int) $id);
            return $this->canRead($user, $resource);
        }

        // User/person/organization graph nodes are safe only when they are not
        // document-derived. Unknown record types are not trusted for household AI/search.
        return in_array($type, ['user'], true);
    }
}
