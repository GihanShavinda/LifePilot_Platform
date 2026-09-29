<?php
namespace App\Domain\Notifications\Controllers;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Requests\UpdateNotificationPreferenceRequest;
use App\Domain\Notifications\Resources\NotificationPreferenceResource;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
class NotificationPreferenceController
{
 private function prefs(Request $r):NotificationPreference{return $r->user()->notificationPreference()->firstOrCreate([],['email_enabled'=>true,'push_enabled'=>true,'reminder_enabled'=>true,'digest_enabled'=>false]);}
 public function show(Request $r){return ApiResponse::success(['preferences'=>new NotificationPreferenceResource($this->prefs($r))]);}
 public function update(UpdateNotificationPreferenceRequest $r,AuditService $audit){$p=$this->prefs($r);$p->update($r->validated());$audit->record('notification_preferences.updated',$r->user(),$p,['fields'=>array_keys($r->validated())]);return ApiResponse::success(['preferences'=>new NotificationPreferenceResource($p)]);}
}
