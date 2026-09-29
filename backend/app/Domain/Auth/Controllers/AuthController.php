<?php

namespace App\Domain\Auth\Controllers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Auth\Enums\HouseholdRole;
use App\Domain\Auth\Requests\LoginRequest;
use App\Domain\Auth\Requests\RegisterRequest;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Profiles\Models\Profile;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use App\Domain\Users\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController
{
    /**
     * Register a new LifePilot user.
     */
    public function register(
        RegisterRequest $request,
        AuditService $audit
    ): JsonResponse {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'timezone' => $data['timezone'],
            ]);

            Profile::create([
                'user_id' => $user->id,
                'first_name' => $data['name'],
                'locale' => 'en',
            ]);

            NotificationPreference::create([
                'user_id' => $user->id,
                'email_enabled' => true,
                'push_enabled' => true,
                'reminder_enabled' => true,
                'digest_enabled' => false,
            ]);

            $household = Household::create([
                'name' => $data['name'] . "'s Household",
            ]);

            HouseholdMember::create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'role' => HouseholdRole::Owner,
            ]);

            return $user;
        });

        /*
        |--------------------------------------------------------------------------
        | Start authenticated web session
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->login($user);

        $request->session()->regenerate();

        /*
        |--------------------------------------------------------------------------
        | Email verification
        |--------------------------------------------------------------------------
        */

        $user->sendEmailVerificationNotification();

        /*
        |--------------------------------------------------------------------------
        | Audit event
        |--------------------------------------------------------------------------
        */

        $audit->record(
            'auth.registered',
            $user,
            $user
        );

        return ApiResponse::success(
            [
                'user' => new UserResource(
                    $user->fresh()
                ),
            ],
            201
        );
    }

    /**
     * Login.
     */
    public function login(
        LoginRequest $request,
        AuditService $audit
    ): JsonResponse {
        $credentials = $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Authenticate using the web/session guard explicitly
        |--------------------------------------------------------------------------
        */

        if (! Auth::guard('web')->attempt(
            [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
            ],
            true
        )) {
            throw ValidationException::withMessages([
                'email' => [
                    'The provided credentials are incorrect.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent session fixation
        |--------------------------------------------------------------------------
        */

        $request->session()->regenerate();

        $user = Auth::guard('web')->user();

        /*
        |--------------------------------------------------------------------------
        | Audit event
        |--------------------------------------------------------------------------
        */

        $audit->record(
            'auth.logged_in',
            $user,
            $user
        );

        return ApiResponse::success([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Logout.
     */
    public function logout(
        Request $request,
        AuditService $audit
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Capture user before logout
        |--------------------------------------------------------------------------
        */

        $user = Auth::guard('web')->user();

        if ($user instanceof User) {
            $audit->record(
                'auth.logged_out',
                $user,
                $user
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Destroy authentication state
        |--------------------------------------------------------------------------
        */

        Auth::guard('web')->logout();

        /*
        |--------------------------------------------------------------------------
        | Destroy existing session
        |--------------------------------------------------------------------------
        */

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        /*
        |--------------------------------------------------------------------------
        | Explicitly clear resolved authenticated user
        |--------------------------------------------------------------------------
        |
        | auth:sanctum may already have resolved the authenticated user for
        | the current request. Clearing the request resolver prevents that
        | user object from remaining attached after logout.
        |
        */

        $request->setUserResolver(
            fn () => null
        );

        /*
        |--------------------------------------------------------------------------
        | Reset Laravel's resolved authentication guards
        |--------------------------------------------------------------------------
        */

        Auth::forgetGuards();

        return ApiResponse::success([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Return currently authenticated user.
     */
    public function me(
        Request $request
    ): JsonResponse {
        return ApiResponse::success([
            'user' => new UserResource(
                $request->user()
            ),
        ]);
    }
}