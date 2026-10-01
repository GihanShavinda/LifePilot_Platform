<?php
namespace App\Domain\Scheduling\Services;
use App\Domain\Scheduling\Contracts\CalendarProvider;
use App\Domain\Scheduling\Models\{CalendarConnection,CalendarEvent};
use Illuminate\Support\Facades\Http;
use RuntimeException;
class GoogleCalendarAdapter implements CalendarProvider {
 private function settings():array{$c=config('lifepilot_scheduling.google');if(!$c['client_id']||!$c['client_secret'])throw new RuntimeException('Google Calendar credentials are not configured.');return $c;}
 public function authorizationUrl(string $state):string{$c=$this->settings();return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>$c['client_id'],'redirect_uri'=>$c['redirect_uri'],'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/calendar.events','access_type'=>'offline','prompt'=>'consent','state'=>$state]);}
 public function exchangeCode(string $code):array{$c=$this->settings();$r=Http::asForm()->post('https://oauth2.googleapis.com/token',['code'=>$code,'client_id'=>$c['client_id'],'client_secret'=>$c['client_secret'],'redirect_uri'=>$c['redirect_uri'],'grant_type'=>'authorization_code'])->throw()->json();if(empty($r['access_token']))throw new RuntimeException('Google OAuth token missing.');return $r;}
 private function token(CalendarConnection $c):string{
  if(!$c->access_token)throw new RuntimeException('Connect Google Calendar before syncing.');
  if($c->token_expires_at && $c->token_expires_at->gt(now()->addMinutes(2)))return $c->access_token;
  if(!$c->refresh_token)throw new RuntimeException('Google authorization expired; reconnect.');
  $s=$this->settings();$r=Http::asForm()->post('https://oauth2.googleapis.com/token',['client_id'=>$s['client_id'],'client_secret'=>$s['client_secret'],'refresh_token'=>$c->refresh_token,'grant_type'=>'refresh_token'])->throw()->json();
  if(empty($r['access_token']))throw new RuntimeException('Google token refresh failed.');
  $c->update(['access_token'=>$r['access_token'],'token_expires_at'=>now()->addSeconds($r['expires_in']??3600)]);return $r['access_token'];
 }
 public function createEvent(CalendarConnection $connection,CalendarEvent $event):string{
  $payload=['summary'=>$event->title,'description'=>$event->description,'location'=>$event->location,'start'=>['dateTime'=>$event->starts_at->setTimezone($event->timezone)->toIso8601String(),'timeZone'=>$event->timezone],'end'=>['dateTime'=>$event->ends_at->setTimezone($event->timezone)->toIso8601String(),'timeZone'=>$event->timezone]];
  $calendar=rawurlencode($connection->external_calendar_id?:'primary');
  // Explicit API call only; never called automatically by scheduler.
  $r=Http::withToken($this->token($connection))->acceptJson()->post("https://www.googleapis.com/calendar/v3/calendars/$calendar/events",$payload)->throw()->json();
  if(empty($r['id']))throw new RuntimeException('Google Calendar event ID missing.');return $r['id'];
 }
}
