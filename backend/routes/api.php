<?php
use App\Domain\Audit\Controllers\AuditLogController;
use App\Domain\Auth\Controllers\AuthController;
use App\Domain\Auth\Controllers\EmailVerificationController;
use App\Domain\Auth\Controllers\PasswordResetController;
use App\Domain\Documents\Controllers\DocumentController;
use App\Domain\Notifications\Controllers\NotificationPreferenceController;
use App\Domain\Profiles\Controllers\ProfileController;
use App\Domain\Users\Controllers\HouseholdController;
use Illuminate\Support\Facades\Route;
Route::prefix('v1')->group(function(){
 Route::get('/health',fn()=>response()->json(['success'=>true,'data'=>['service'=>'lifepilot-api','status'=>'ok']]));
 Route::prefix('auth')->group(function(){Route::post('/register',[AuthController::class,'register'])->middleware('throttle:6,1');Route::post('/login',[AuthController::class,'login'])->middleware('throttle:10,1');Route::post('/forgot-password',[PasswordResetController::class,'forgot'])->middleware('throttle:5,1');Route::post('/reset-password',[PasswordResetController::class,'reset'])->middleware('throttle:5,1');Route::middleware('auth:sanctum')->group(function(){Route::post('/logout',[AuthController::class,'logout']);Route::get('/me',[AuthController::class,'me']);Route::post('/email/verification-notification',[EmailVerificationController::class,'send'])->middleware('throttle:6,1');Route::get('/email/verify/{id}/{hash}',[EmailVerificationController::class,'verify'])->middleware(['signed','throttle:6,1'])->name('verification.verify');});});
 Route::middleware('auth:sanctum')->group(function(){Route::get('/profile',[ProfileController::class,'show']);Route::put('/profile',[ProfileController::class,'update']);Route::delete('/profile',[ProfileController::class,'destroy']);Route::get('/notification-preferences',[NotificationPreferenceController::class,'show']);Route::put('/notification-preferences',[NotificationPreferenceController::class,'update']);Route::get('/households/current',[HouseholdController::class,'current']);Route::get('/households/current/members',[HouseholdController::class,'members']);Route::get('/audit-logs',[AuditLogController::class,'index']);Route::get('/owner-check',fn()=>response()->json(['success'=>true,'data'=>['authorized'=>true]]))->middleware('household.role:owner');
  Route::get('/document-categories',[DocumentController::class,'categories']); Route::get('/documents',[DocumentController::class,'index']); Route::post('/documents',[DocumentController::class,'store'])->middleware('throttle:30,1'); Route::get('/documents/{id}',[DocumentController::class,'show']); Route::put('/documents/{id}',[DocumentController::class,'update']); Route::post('/documents/{id}/replace',[DocumentController::class,'replace']); Route::post('/documents/{id}/archive',[DocumentController::class,'archive']); Route::post('/documents/{id}/restore',[DocumentController::class,'restoreArchive']); Route::delete('/documents/{id}',[DocumentController::class,'destroy']); Route::post('/documents/{id}/signed-url',[DocumentController::class,'signedUrl']);
 });
});
Route::get('/documents/{document}/versions/{version}/download',[DocumentController::class,'download'])->middleware(['auth:sanctum','signed'])->name('documents.signed-download');
