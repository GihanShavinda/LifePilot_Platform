<?php
namespace App\Domain\Notifications\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class NotificationPreferenceResource extends JsonResource { public static $wrap=null; public function toArray(Request $request):array{return ['email_enabled'=>$this->email_enabled,'push_enabled'=>$this->push_enabled,'reminder_enabled'=>$this->reminder_enabled,'digest_enabled'=>$this->digest_enabled];} }
