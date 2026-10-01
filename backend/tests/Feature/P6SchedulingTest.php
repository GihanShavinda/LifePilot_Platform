<?php
namespace Tests\Feature;
use App\Domain\Scheduling\Models\{CalendarEvent,Notification,NotificationSetting};
use App\Domain\Scheduling\Services\{CalendarService,CalendarRecurrence,NotificationEngine};
use App\Domain\Users\Models\{Household,HouseholdMember,User};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
class P6SchedulingTest extends TestCase {
 use RefreshDatabase;
 protected function setUp():void{parent::setUp();\Illuminate\Support\Facades\Event::fake([\App\Domain\Scheduling\Events\LifePilotUpdated::class]);}
 private function actor(string $role='owner'):array{$u=User::factory()->create(['timezone'=>'Asia/Colombo']);$h=Household::create(['name'=>'Test household']);HouseholdMember::create(['user_id'=>$u->id,'household_id'=>$h->id,'role'=>$role]);Sanctum::actingAs($u);return [$u,$h];}
 public function test_calendar_timezone_and_conflicts_require_confirmation():void{
  $this->actor();$a=['title'=>'Appointment','starts_at'=>'2026-11-08T09:00','ends_at'=>'2026-11-08T10:00','timezone'=>'Asia/Colombo','reminder_offsets'=>[7,1,0]];
  $id=$this->postJson('/api/v1/calendar/events',$a)->assertCreated()->json('data.event.id');
  $event=CalendarEvent::findOrFail($id);$this->assertSame('03:30',$event->starts_at->utc()->format('H:i'));
  $this->postJson('/api/v1/calendar/events',array_merge($a,['title'=>'Conflict']))->assertStatus(409);
  $this->postJson('/api/v1/calendar/events',array_merge($a,['title'=>'Reviewed conflict','confirm_conflicts'=>true]))->assertCreated();
 }
 public function test_household_and_viewer_authorization():void{
  [$owner,$h]=$this->actor();$id=$this->postJson('/api/v1/calendar/events',['title'=>'Private','starts_at'=>'2026-11-08T09:00','ends_at'=>'2026-11-08T10:00','timezone'=>'UTC'])->assertCreated()->json('data.event.id');
  $this->actor('viewer');$this->getJson("/api/v1/calendar/events/$id")->assertNotFound();$this->putJson("/api/v1/calendar/events/$id",['title'=>'Intrusion'])->assertNotFound();
  [$viewer,$same]=$this->actor('viewer');$own=CalendarEvent::create(['household_id'=>$same->id,'user_id'=>$viewer->id,'title'=>'Readable','starts_at'=>now()->addHour(),'ends_at'=>now()->addHours(2),'timezone'=>'UTC']);
  $this->getJson("/api/v1/calendar/events/$own->id")->assertOk();$this->deleteJson("/api/v1/calendar/events/$own->id")->assertForbidden();
 }
 public function test_notification_deduplication_and_quiet_hours():void{
  Queue::fake();[$u,$h]=$this->actor();NotificationSetting::create(['user_id'=>$u->id,'timezone'=>'Asia/Colombo','quiet_start'=>'22:00','quiet_end'=>'07:00']);
  $engine=app(NotificationEngine::class);$time=CarbonImmutable::parse('2026-11-08 23:00','Asia/Colombo')->utc();
  $a=$engine->schedule($u,$h->id,'reminder','Important task','Pay the bill','unique-test',$time);
  $b=$engine->schedule($u,$h->id,'reminder','Important task','Pay the bill','unique-test',$time);
  $this->assertSame($a->id,$b->id);$this->assertDatabaseCount('life_notifications',1);
  $this->assertSame('2026-11-09 07:00',$engine->effectiveTime($a,'email')->setTimezone('Asia/Colombo')->format('Y-m-d H:i'));
  $a->update(['scheduled_at'=>CarbonImmutable::parse('2026-11-09 06:00','Asia/Colombo')->utc()]);
  $this->assertSame('2026-11-09 07:00',$engine->effectiveTime($a->fresh(),'email')->setTimezone('Asia/Colombo')->format('Y-m-d H:i'));
 }
 public function test_external_google_write_is_never_implicit():void{
  $this->actor();$id=$this->postJson('/api/v1/calendar/events',['title'=>'Local only','starts_at'=>'2026-11-08T09:00','ends_at'=>'2026-11-08T10:00','timezone'=>'UTC'])->assertCreated()->json('data.event.id');
  $this->assertNull(CalendarEvent::find($id)->external_event_id);
  $this->postJson("/api/v1/calendar/events/$id/sync-google")->assertUnprocessable();
 }
 public function test_failed_push_attempt_records_retries_then_terminal_failure():void{
  Queue::fake();[$user,$household]=$this->actor();
  \App\Domain\Notifications\Models\NotificationPreference::updateOrCreate(['user_id'=>$user->id],['email_enabled'=>false,'push_enabled'=>true,'reminder_enabled'=>true,'digest_enabled'=>false]);
  $engine=app(NotificationEngine::class);$notice=$engine->schedule($user,$household->id,'test','Push test','Delivery test','push-failure',CarbonImmutable::now());
  $notice->deliveries()->create(['channel'=>'push','status'=>'pending','next_attempt_at'=>now()->subMinute()]);
  $adapter=new class implements \App\Domain\Scheduling\Contracts\PushAdapter{public function send(\App\Domain\Scheduling\Models\Notification $notice):string{throw new \RuntimeException('provider unavailable');}};
  for($i=1;$i<=3;$i++){
   \App\Domain\Scheduling\Models\NotificationDelivery::where('notification_id',$notice->id)->where('channel','push')->update(['next_attempt_at'=>now()->subMinute()]);
   (new \App\Domain\Scheduling\Jobs\DeliverNotification($notice->id))->handle($engine,$adapter);
  }
  $delivery=\App\Domain\Scheduling\Models\NotificationDelivery::where('notification_id',$notice->id)->where('channel','push')->first();
  $this->assertSame(3,$delivery->attempts);$this->assertSame('failed',$delivery->status);$this->assertNotNull($delivery->last_error);
 }

}
