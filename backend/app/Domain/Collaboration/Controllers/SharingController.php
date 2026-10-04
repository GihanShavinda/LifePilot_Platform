<?php

namespace App\Domain\Collaboration\Controllers;

use App\Domain\Collaboration\Enums\SharingScope;
use App\Domain\Collaboration\Services\{HouseholdActivityService, SharedResourceService};
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Validation\Rule;

class SharingController
{
    public function __construct(
        private SharedResourceService $sharing,
        private HouseholdActivityService $activity,
    ) {
    }

    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $resource = $this->sharing->resolve($type, $id);
        $this->sharing->ensureRead($request->user(), $resource);

        return ApiResponse::success([
            'resource_type' => $type,
            'resource_id' => $id,
            'scope' => $this->sharing->scope($resource)->value,
            'owner_user_id' => $resource->user_id,
        ]);
    }

    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate(['scope' => ['required', Rule::in(['private', 'household'])]]);
        $resource = $this->sharing->resolve($type, $id);
        $scope = SharingScope::from($data['scope']);
        $row = $this->sharing->share($request->user(), $resource, $scope);

        $this->activity->record(
            (int) $resource->household_id,
            $request->user(),
            'resource.sharing_changed',
            ucfirst(str_replace('_', ' ', $type)).' sharing changed to '.$scope->value.'.',
            $type,
            $id,
            ['scope' => $scope->value],
            true
        );

        return ApiResponse::success(['sharing' => $row]);
    }
}
