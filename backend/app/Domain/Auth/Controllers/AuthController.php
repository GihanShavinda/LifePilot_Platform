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
    public function register(RegisterRequest $request, AuditService $audit): JsonResponse
    {
        $data = $request->validated();
        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'timezone' => $data['timezone']]);
            Profile::create(['user_id' => $user->id, 'first_name' => $data['name'], 'locale' => 'en']);
            NotificationPreference::create(['user_id' => $user->id, 'email_enabled' => true, 'push_enabled' => true, 'reminder_enabled' => true, 'digest_enabled' => false]);
            $household = Household::create(['name' => $data['name'] . "'s Household"]);
            HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => HouseholdRole::Owner]);
            return $user;
        });
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->sendEmailVerificationNotification();
        $audit->record('auth.registered', $user, $user);
        return ApiResponse::success(['user' => new UserResource($user->fresh())], 201);
    }
    public function login(LoginRequest $request, AuditService $audit): JsonResponse
    {
        $c = $request->validated();
        if (!Auth::guard('web')->attempt(['email' => $c['email'], 'password' => $c['password']], true)) throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        $request->session()->regenerate();
        $user = Auth::guard('web')->user();
        $audit->record('auth.logged_in', $user, $user);
        return ApiResponse::success(['user' => new UserResource($user)]);
    }
    public function logout(Request $request, AuditService $audit): JsonResponse
    {
        $user = Auth::guard('web')->user();
        if ($user instanceof User) $audit->record('auth.logged_out', $user, $user);
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        $request->setUserResolver(fn() => null);
        Auth::forgetGuards();
        return ApiResponse::success(['message' => 'Logged out successfully.']);
    }
    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => new UserResource($request->user())]);
    }
}
