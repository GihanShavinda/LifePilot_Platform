<?php
namespace App\Domain\Scheduling\Controllers;
use App\Domain\Scheduling\Models\{CalendarConnection,CalendarEvent};
use App\Domain\Scheduling\Services\{CalendarAccess,GoogleCalendarAdapter};
use App\Support\ApiResponse;
use Illuminate\Http\{Request,JsonResponse};
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
class CalendarConnectionController {
 public function __construct(private CalendarAccess $access,private GoogleCalendarAdapter $google){}
 public function status(Request $r):JsonResponse{$h=$this->access->household($r->user());return ApiResponse::success(['connection'=>CalendarConnection::where('user_id',$r->user()->id)->where('household_id',$h)->first(['id','provider','status','authorized_at','scopes','external_calendar_id'])]);}
 public function begin(Request $r):JsonResponse{
  $h=$this->access->household($r->user(),true);$state=bin2hex(random_bytes(32));Cache::put('google-calendar-oauth:'.hash('sha256',$state),['user_id'=>$r->user()->id,'household_id'=>$h],now()->addMinutes(10));
  return ApiResponse::success(['authorization_url'=>$this->google->authorizationUrl($state)]);
 }
 public function callback(Request $r){
  $d=$r->validate(['state'=>'required|string','code'=>'required|string']);$data=Cache::pull('google-calendar-oauth:'.hash('sha256',$d['state']));
  if(!$data||$data['user_id']!==$r->user()->id)abort(403,'Expired or mismatched OAuth state.');$this->access->household($r->user(),true,$data['household_id']);
  $tokens=$this->google->exchangeCode($d['code']);
  CalendarConnection::updateOrCreate(['user_id'=>$r->user()->id,'household_id'=>$data['household_id'],'provider'=>'google'],['access_token'=>$tokens['access_token'],'refresh_token'=>$tokens['refresh_token']??CalendarConnection::where('user_id',$r->user()->id)->where('household_id',$data['household_id'])->first()?->refresh_token,'token_expires_at'=>now()->addSeconds($tokens['expires_in']??3600),'scopes'=>explode(' ',$tokens['scope']??''),'status'=>'connected','authorized_at'=>now()]);
  return redirect(rtrim(env('FRONTEND_URL','http://localhost:5174'),'/').'/calendar?google=connected');
 }
 public function disconnect(Request $r):JsonResponse{
  $h=$this->access->household($r->user(),true);CalendarConnection::where('user_id',$r->user()->id)->where('household_id',$h)->where('provider','google')->update(['access_token'=>null,'refresh_token'=>null,'status'=>'disconnected','authorized_at'=>null]);
  return ApiResponse::success(['disconnected'=>true]);
 }
 public function syncEvent(Request $r,int $id):JsonResponse{
  $event=$this->access->event($r->user(),$id,true);$connection=CalendarConnection::where('user_id',$r->user()->id)->where('household_id',$event->household_id)->where('provider','google')->where('status','connected')->first();
  if(!$connection)throw ValidationException::withMessages(['google'=>'Connect Google Calendar and authorize access first.']);
  if($event->external_event_id)throw ValidationException::withMessages(['event'=>'This event was already exported.']);
  $external=$this->google->createEvent($connection,$event);
  $event->update(['calendar_connection_id'=>$connection->id,'external_event_id'=>$external]);
  return ApiResponse::success(['event'=>$event,'external_event_id'=>$external]);
 }
}
