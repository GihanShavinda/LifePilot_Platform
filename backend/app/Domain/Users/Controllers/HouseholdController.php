<?php

namespace App\Domain\Users\Controllers;

use App\Domain\Collaboration\Services\HouseholdAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class HouseholdController
{
    public function __construct(private HouseholdAccessService $access)
    {
    }

    public function current(Request $request)
    {
        $membership = $this->access->membership($request->user());
        $role = $membership->role instanceof \BackedEnum ? $membership->role->value : (string) $membership->role;

        return ApiResponse::success([
            'household' => [
                'id' => $membership->household->id,
                'name' => $membership->household->name,
                'role' => $this->access->normalizedRole($role),
            ],
        ]);
    }

    public function members(Request $request)
    {
        $membership = $this->access->membership($request->user());
        $members = $membership->household->memberships()
            ->with('user:id,name,email')
            ->paginate(min((int) $request->integer('per_page', 15), 100));

        return ApiResponse::success(
            $members->items(),
            200,
            ['pagination' => [
                'current_page' => $members->currentPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'last_page' => $members->lastPage(),
            ]]
        );
    }
}
