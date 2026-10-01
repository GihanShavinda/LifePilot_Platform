<?php
namespace App\Domain\Scheduling\Controllers;
use App\Domain\Scheduling\Models\{Notification,NotificationDelivery,NotificationSetting,BrowserPushSubscription};
use App\Domain\Scheduling\Services\CalendarAccess;
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
class NotificationController {
 public function __construct(private CalendarAccess $access){}
 public function index(Request $r):JsonResponse{
  $households=$r->user()->householdMemberships()->pluck('household_id');$q=Notification::where('user_id',$r->user()->id)->whereIn('household_id',$households)->with('deliveries');
  if($r->boolean('unread'))$q->whereNull('read_at');
  return ApiResponse::success(['notifications'=>$q->latest()->paginate(30)]);
 }
 public function read(Request $r,int $id):JsonResponse{
  $n=Notification::where('user_id',$r->user()->id)->findOrFail($id);$this->access->household($r->user(),false,$n->household_id);$n->update(['read_at'=>now()]);return ApiResponse::success(['notification'=>$n]);
 }
 public function settings(Request $r):JsonResponse{
  $settings=NotificationSetting::firstOrCreate(['user_id'=>$r->user()->id],['timezone'=>$r->user()->timezone?:'UTC']);return ApiResponse::success(['settings'=>$settings]);
 }
 public function saveSettings(Request $r):JsonResponse{
  $d=$r->validate(['quiet_start'=>'nullable|date_format:H:i','quiet_end'=>'nullable|date_format:H:i','timezone'=>'required|timezone','browser_enabled'=>'boolean','in_app_enabled'=>'boolean','escalation_enabled'=>'boolean']);
  $s=NotificationSetting::updateOrCreate(['user_id'=>$r->user()->id],$d);return ApiResponse::success(['settings'=>$s]);
 }
 public function subscribePush(Request $r):JsonResponse{
  $d=$r->validate(['endpoint'=>'required|url|max:2000','keys.p256dh'=>'required|string|max:512','keys.auth'=>'required|string|max:512']);
  // Adapter registration only: disabled provider must never imply push was delivered.
  $s=BrowserPushSubscription::updateOrCreate(['endpoint_hash'=>hash('sha256',$d['endpoint'])],['user_id'=>$r->user()->id,'endpoint'=>$d['endpoint'],'p256dh'=>$d['keys']['p256dh'],'auth_secret'=>$d['keys']['auth'],'last_seen_at'=>now()]);
  return ApiResponse::success(['registered'=>true,'provider_enabled'=>config('lifepilot_scheduling.push.provider')!=='disabled'],201);
 }
}
