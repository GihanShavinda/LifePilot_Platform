<?php
namespace Tests\Unit;
use App\Domain\Scheduling\Models\Notification;
use App\Domain\Scheduling\Services\NotificationEngine;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Domain\Users\Models\{Household,HouseholdMember,User};
use Tests\TestCase;
class P6NotificationPolicyTest extends TestCase {
 use RefreshDatabase;
 public function test_duplicate_notification_delivery_does_not_duplicate_rows():void{
  \Illuminate\Support\Facades\Queue::fake();
  $user=User::factory()->create();$home=Household::create(['name'=>'Example']);HouseholdMember::create(['household_id'=>$home->id,'user_id'=>$user->id,'role'=>'owner']);
  $engine=app(NotificationEngine::class);
  $first=$engine->schedule($user,$home->id,'reminder','Renewal','Renew policy','policy-1',CarbonImmutable::now());
  $second=$engine->schedule($user,$home->id,'reminder','Renewal','Renew policy','policy-1',CarbonImmutable::now());
  $this->assertSame($first->id,$second->id);$this->assertDatabaseCount('life_notifications',1);
 }
}
