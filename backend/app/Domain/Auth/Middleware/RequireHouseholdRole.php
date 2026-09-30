<?php

namespace App\Domain\Auth\Middleware;

use BackedEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHouseholdRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        abort_unless(
            $user !== null,
            401,
            'Authentication is required.'
        );

        $membership = $user
            ->householdMemberships()
            ->first();

        abort_unless(
            $membership !== null,
            403,
            'Household membership is required.'
        );

        // Support both string and enum role values.
        $role = $membership->role instanceof BackedEnum
            ? $membership->role->value
            : (string) $membership->role;

        abort_unless(
            in_array($role, $roles, true),
            403,
            'Your household role does not permit this action.'
        );

        return $next($request);
    }
}