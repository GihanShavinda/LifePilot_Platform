<?php

use App\Domain\Audit\Controllers\AuditLogController;
use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Auth\Controllers\EmailVerificationController;
use App\Domain\Auth\Controllers\PasswordResetController;
use App\Domain\Documents\Controllers\DocumentController;
use App\Domain\Documents\Controllers\DocumentIntelligenceController;
use App\Domain\Notifications\Controllers\NotificationPreferenceController;
use App\Domain\Profiles\Controllers\ProfileController;
use App\Domain\Users\Controllers\HouseholdController;
use Illuminate\Support\Facades\Route;
use App\Domain\Obligations\Controllers\{ObligationController,TaskController};

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json([
        'success' => true,
        'data' => [
            'service' => 'lifepilot-api',
            'status' => 'ok',
        ],
    ]));

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:6,1');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::post('/forgot-password', [PasswordResetController::class, 'forgot'])
            ->middleware('throttle:5,1');

        Route::post('/reset-password', [PasswordResetController::class, 'reset'])
            ->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);

            Route::post(
                '/email/verification-notification',
                [EmailVerificationController::class, 'send']
            )->middleware('throttle:6,1');

            Route::get(
                '/email/verify/{id}/{hash}',
                [EmailVerificationController::class, 'verify']
            )->middleware(['signed', 'throttle:6,1'])
                ->name('verification.verify');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::delete('/profile', [ProfileController::class, 'destroy']);

        Route::get(
            '/notification-preferences',
            [NotificationPreferenceController::class, 'show']
        );
        Route::put(
            '/notification-preferences',
            [NotificationPreferenceController::class, 'update']
        );

        Route::get('/households/current', [HouseholdController::class, 'current']);
        Route::get('/households/current/members', [HouseholdController::class, 'members']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/owner-check', fn () => response()->json([
            'success' => true,
            'data' => ['authorized' => true],
        ]))->middleware('household.role:owner');

        Route::get('/document-categories', [DocumentController::class, 'categories']);
        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store'])
            ->middleware('throttle:30,1');

        Route::get('/documents/{id}', [DocumentController::class, 'show']);
        Route::put('/documents/{id}', [DocumentController::class, 'update']);
        Route::post('/documents/{id}/replace', [DocumentController::class, 'replace']);
        Route::post('/documents/{id}/archive', [DocumentController::class, 'archive']);
        Route::post('/documents/{id}/restore', [DocumentController::class, 'restoreArchive']);
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);
        Route::post('/documents/{id}/signed-url', [DocumentController::class, 'signedUrl']);

        // P4 obligations: source suggestions are read-only until explicit approval.
        Route::get('/obligations', [ObligationController::class, 'index']);
        Route::post('/obligations', [ObligationController::class, 'store']);
        Route::post('/obligations/{id}/approve', [ObligationController::class, 'approve']);
        Route::post('/obligations/{id}/dismiss', [ObligationController::class, 'dismiss']);
        Route::get('/documents/{documentId}/obligation-suggestions', [ObligationController::class, 'suggestions']);
        Route::post('/documents/{documentId}/obligation-suggestions/approve', [ObligationController::class, 'approveSuggestion']);

        Route::get('/tasks', [TaskController::class, 'index']);
        Route::post('/tasks', [TaskController::class, 'store']);
        Route::get('/task-reminders', [TaskController::class, 'dueReminders']);
        Route::get('/tasks/{id}', [TaskController::class, 'show']);
        Route::put('/tasks/{id}', [TaskController::class, 'update']);
        Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
        Route::get('/tasks/{id}/timeline', [TaskController::class, 'timeline']);
        Route::post('/tasks/{id}/checklist', [TaskController::class, 'addChecklist']);
        Route::put('/tasks/{id}/checklist/{itemId}', [TaskController::class, 'updateChecklist']);
        Route::delete('/tasks/{id}/checklist/{itemId}', [TaskController::class, 'deleteChecklist']);
        Route::post('/tasks/{id}/dependencies', [TaskController::class, 'addDependency']);
        Route::delete('/tasks/{id}/dependencies/{dependencyId}', [TaskController::class, 'removeDependency']);
        Route::post('/tasks/{id}/reminders', [TaskController::class, 'addReminder']);
        Route::post('/tasks/{id}/reminders/{reminderId}/snooze', [TaskController::class, 'snoozeReminder']);

        // P3 document intelligence
        Route::get(
            '/documents/{id}/intelligence',
            [DocumentIntelligenceController::class, 'show']
        );

        Route::get(
            '/documents/{id}/intelligence/versions',
            [DocumentIntelligenceController::class, 'versions']
        );

        Route::post(
            '/documents/{id}/intelligence/reprocess',
            [DocumentIntelligenceController::class, 'reprocess']
        )->middleware('throttle:10,1');

        Route::put(
            '/documents/{documentId}/intelligence/fields/{fieldId}/review',
            [DocumentIntelligenceController::class, 'reviewField']
        );
    });
});

Route::get(
    '/documents/{document}/versions/{version}/download',
    [DocumentController::class, 'download']
)->middleware(['auth:sanctum', 'signed'])
    ->name('documents.signed-download');
