<?php
namespace App\Domain\Auth\Controllers;
use App\Domain\Audit\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
class EmailVerificationController
{
 public function send(Request $request){ if($request->user()->hasVerifiedEmail()) return ApiResponse::success(['message'=>'Email already verified.']); $request->user()->sendEmailVerificationNotification(); return ApiResponse::success(['message'=>'Verification email sent.']); }
 public function verify(EmailVerificationRequest $request, AuditService $audit){ if(!$request->user()->hasVerifiedEmail()){$request->fulfill();$audit->record('auth.email_verified',$request->user(),$request->user());} return ApiResponse::success(['message'=>'Email verified.']); }
}
