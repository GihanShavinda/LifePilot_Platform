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
use App\Domain\Scheduling\Controllers\{CalendarController,CalendarConnectionController,NotificationController};
use App\Domain\Finance\Controllers\{FinanceController,SubscriptionController,AssetController,FinanceCsvController,RecurringExpenseController};
use App\Domain\Obligations\Controllers\{ObligationController,TaskController};
use App\Domain\Graph\Controllers\{GraphController,SemanticSearchController};
use App\Domain\Assistant\Controllers\AssistantController;

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

        // P5 finance: all routes are Sanctum-protected and household-scoped.
        Route::get('/finance/dashboard', [FinanceController::class, 'dashboard']);
        Route::get('/finance/categories', [FinanceController::class, 'categories']);
        Route::post('/finance/categories', [FinanceController::class, 'saveCategory']);
        Route::get('/finance/expenses', [FinanceController::class, 'expenses']);
        Route::post('/finance/expenses', [FinanceController::class, 'createExpense']);
        Route::put('/finance/expenses/{id}', [FinanceController::class, 'updateExpense']);
        Route::delete('/finance/expenses/{id}', [FinanceController::class, 'deleteExpense']);
        Route::get('/finance/expenses/{id}/recurrence', [RecurringExpenseController::class, 'preview']);
        Route::post('/finance/expenses/{id}/recurrence/confirm', [RecurringExpenseController::class, 'confirm']);
        Route::get('/finance/expenses.csv', [FinanceCsvController::class, 'export']);
        Route::post('/finance/expenses/import', [FinanceCsvController::class, 'import']);
        Route::get('/documents/{id}/receipt-expense-suggestion', [FinanceController::class, 'receiptPreview']);
        Route::post('/documents/{id}/receipt-expense-suggestion/accept', [FinanceController::class, 'acceptReceipt']);
        Route::get('/finance/subscriptions', [SubscriptionController::class, 'index']);
        Route::post('/finance/subscriptions', [SubscriptionController::class, 'create']);
        Route::put('/finance/subscriptions/{id}', [SubscriptionController::class, 'update']);
        Route::delete('/finance/subscriptions/{id}', [SubscriptionController::class, 'remove']);
        Route::post('/finance/subscriptions/{id}/payments', [SubscriptionController::class, 'recordPayment']);
        Route::get('/finance/subscription-insights', [SubscriptionController::class, 'insights']);
        Route::get('/finance/assets', [AssetController::class, 'index']);
        Route::post('/finance/assets', [AssetController::class, 'create']);
        Route::put('/finance/assets/{id}', [AssetController::class, 'update']);
        Route::delete('/finance/assets/{id}', [AssetController::class, 'remove']);
        Route::post('/finance/asset-categories', [AssetController::class, 'category']);
        Route::post('/finance/assets/{id}/warranties', [AssetController::class, 'addWarranty']);
        Route::put('/finance/assets/{id}/warranties/{warrantyId}', [AssetController::class, 'updateWarranty']);
        Route::post('/finance/assets/{id}/maintenance', [AssetController::class, 'addMaintenance']);

        // P6 internal calendar and notification inbox. All actions require Sanctum.
        Route::get('/calendar/events', [CalendarController::class,'index']);
        Route::get('/calendar/conflicts', [CalendarController::class,'conflicts']);
        Route::post('/calendar/events', [CalendarController::class,'store']);
        Route::post('/calendar/tasks/{taskId}/event', [CalendarController::class,'fromTask']);
        Route::post('/calendar/documents/{documentId}/appointment', [CalendarController::class,'fromDocument']);
        Route::get('/calendar/events/{id}', [CalendarController::class,'show']);
        Route::put('/calendar/events/{id}', [CalendarController::class,'update']);
        Route::delete('/calendar/events/{id}', [CalendarController::class,'destroy']);
        Route::get('/calendar/google', [CalendarConnectionController::class,'status']);
        Route::post('/calendar/google/connect', [CalendarConnectionController::class,'begin']);
        Route::get('/calendar/google/callback', [CalendarConnectionController::class,'callback']);
        Route::delete('/calendar/google', [CalendarConnectionController::class,'disconnect']);
        Route::post('/calendar/events/{id}/sync-google', [CalendarConnectionController::class,'syncEvent']);
        Route::get('/notifications', [NotificationController::class,'index']);
        Route::put('/notifications/{id}/read', [NotificationController::class,'read']);
        Route::get('/notifications/settings', [NotificationController::class,'settings']);
        Route::put('/notifications/settings', [NotificationController::class,'saveSettings']);
        Route::post('/notifications/push-subscriptions', [NotificationController::class,'subscribePush']);

        // P8 grounded AI assistant. Sessions and evidence are strictly household-scoped.
        Route::get('/assistant/sessions', [AssistantController::class, 'sessions']);
        Route::post('/assistant/sessions', [AssistantController::class, 'createSession']);
        Route::get('/assistant/sessions/{id}', [AssistantController::class, 'show']);
        Route::post('/assistant/sessions/{id}/messages', [AssistantController::class, 'ask'])->middleware('throttle:30,1');
        Route::post('/assistant/messages/{messageId}/feedback', [AssistantController::class, 'feedback']);

        // P7 Life Action Graph + semantic/hybrid search.
        Route::post('/graph/sync', [GraphController::class, 'sync']);
        Route::get('/graph/entities', [GraphController::class, 'entities']);
        Route::get('/graph/entities/{id}', [GraphController::class, 'show']);
        Route::get('/search', [SemanticSearchController::class, 'search']);
        Route::post('/documents/{id}/semantic/reindex', [SemanticSearchController::class, 'reindexDocument']);

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
