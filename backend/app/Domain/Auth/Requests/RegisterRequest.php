<?php
namespace App\Domain\Auth\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
class RegisterRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['name'=>['required','string','max:120'],'email'=>['required','email','max:255','unique:users,email'],'password'=>['required','confirmed',Password::min(10)->mixedCase()->numbers()],'timezone'=>['required','timezone:all']];} }
