<?php
namespace App\Domain\Auth\Controllers;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
class PasswordResetController
{
 public function forgot(Request $request){$request->validate(['email'=>['required','email']]); Password::sendResetLink($request->only('email')); return ApiResponse::success(['message'=>'If the address exists, a password reset link has been sent.']);}
 public function reset(Request $request){$request->validate(['token'=>['required'],'email'=>['required','email'],'password'=>['required','confirmed',PasswordRule::min(10)->mixedCase()->numbers()]]); $status=Password::reset($request->only('email','password','password_confirmation','token'),function($user,$password){$user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();}); if($status!==Password::PasswordReset) return ApiResponse::error('PASSWORD_RESET_FAILED',__($status),422); return ApiResponse::success(['message'=>__($status)]);}
}
