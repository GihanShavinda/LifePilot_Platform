<?php
namespace App\Domain\Notifications\Requests;
use Illuminate\Foundation\Http\FormRequest;
class UpdateNotificationPreferenceRequest extends FormRequest { public function authorize():bool{return true;} public function rules():array{return ['email_enabled'=>['sometimes','boolean'],'push_enabled'=>['sometimes','boolean'],'reminder_enabled'=>['sometimes','boolean'],'digest_enabled'=>['sometimes','boolean']];} }
