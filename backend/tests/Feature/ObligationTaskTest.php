<?php
namespace Tests\Feature;

use App\Domain\Documents\Models\{Document,DocumentVersion,DocumentExtraction,ExtractedField};
use App\Domain\Obligations\Models\{Obligation,Task};
use App\Domain\Users\Models\{User,Household,HouseholdMember};
use App\Domain\Obligations\Services\RecurrenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ObligationTaskTest extends TestCase
{
 use RefreshDatabase;
 private function actor():array {
  $u=User::factory()->create();$h=Household::create(['name'=>'Test household']);
  HouseholdMember::create(['user_id'=>$u->id,'household_id'=>$h->id,'role'=>'owner']);Sanctum::actingAs($u);
  return [$u,$h];
 }
 private function doc(User $u,Household $h):array {
  $doc=Document::create(['user_id'=>$u->id,'household_id'=>$h->id,'title'=>'Electricity bill','original_filename'=>'bill.txt','mime_type'=>'text/plain','size'=>10,'storage_disk'=>'documents','storage_path'=>'bill.txt','checksum'=>str_repeat('a',64),'status'=>'active','processing_status'=>'ready']);
  $v=DocumentVersion::create(['document_id'=>$doc->id,'uploaded_by'=>$u->id,'version_number'=>1,'original_filename'=>'bill.txt','mime_type'=>'text/plain','size'=>10,'storage_disk'=>'documents','storage_path'=>'bill.txt','checksum'=>str_repeat('a',64)]);
  $ex=DocumentExtraction::create(['document_id'=>$doc->id,'document_version_id'=>$v->id,'version_number'=>1,'status'=>'needs_review','extractor_version'=>'test','source_text'=>'Amount Due 8450.00 Due Date '.now()->addMonth()->toDateString()]);
  foreach(['document_type'=>'bill','due_date'=>now()->addMonth()->toDateString(),'amount'=>'8450','currency'=>'LKR'] as $name=>$value)ExtractedField::create(['document_extraction_id'=>$ex->id,'field_name'=>$name,'value'=>$value,'normalized_value'=>$value,'confidence'=>.9,'page'=>1,'evidence_text'=>$value,'extractor_version'=>'test','review_status'=>'accepted','source'=>'deterministic','fingerprint'=>hash('sha256',$name)]);
  return [$doc,$ex];
 }
 public function test_suggestions_need_accepted_fields_and_explicit_approval():void {
  [$u,$h]=$this->actor();[$doc,$ex]=$this->doc($u,$h);
  $resp=$this->getJson("/api/v1/documents/{$doc->id}/obligation-suggestions")->assertOk();
  $key=$resp->json('data.suggestions.0.dedupe_key');$this->assertNotNull($key);
  $this->assertDatabaseCount('tasks',0);
  $this->postJson("/api/v1/documents/{$doc->id}/obligation-suggestions/approve",['dedupe_key'=>$key])->assertCreated();
  $this->assertDatabaseCount('tasks',1);
  $this->postJson("/api/v1/documents/{$doc->id}/obligation-suggestions/approve",['dedupe_key'=>$key])->assertStatus(409);
  $this->assertDatabaseCount('obligations',1);
 }
 public function test_manual_duplicate_and_past_due_date_blocked():void {
  $this->actor();$data=['type'=>'payment','title'=>'Pay water bill','due_at'=>now()->addDays(10)->toIso8601String()];
  $this->postJson('/api/v1/obligations',$data)->assertCreated();
  $this->postJson('/api/v1/obligations',$data)->assertUnprocessable();
  $this->postJson('/api/v1/obligations',array_merge($data,['title'=>'Old','due_at'=>now()->subDay()->toIso8601String()]))->assertUnprocessable();
 }
 public function test_completion_cancels_reminders_and_dependencies_block_completion():void {
  $this->actor();$one=$this->postJson('/api/v1/tasks',['title'=>'Prepare funds','due_at'=>now()->addDays(10)->toIso8601String()])->assertCreated()->json('data.task.id');
  $two=$this->postJson('/api/v1/tasks',['title'=>'Pay bill','due_at'=>now()->addDays(11)->toIso8601String()])->assertCreated()->json('data.task.id');
  $this->postJson("/api/v1/tasks/{$two}/dependencies",['depends_on_task_id'=>$one])->assertOk();
  $this->putJson("/api/v1/tasks/{$two}",['status'=>'completed'])->assertUnprocessable();
  $this->putJson("/api/v1/tasks/{$one}",['status'=>'completed','completion_evidence'=>'Transfer confirmation'])->assertOk();
  $this->putJson("/api/v1/tasks/{$two}",['status'=>'completed'])->assertOk();
  $this->assertEquals(0,Task::find($two)->reminders()->where('status','scheduled')->count());
  $this->postJson("/api/v1/tasks/{$two}/reminders",['remind_at'=>now()->addDay()->toIso8601String()])->assertUnprocessable();
 }
 public function test_cross_household_isolation():void {
  [$u,$h]=$this->actor();$task=Task::create(['household_id'=>$h->id,'user_id'=>$u->id,'title'=>'Private','status'=>'pending']);
  $this->actor();$this->getJson("/api/v1/tasks/{$task->id}")->assertNotFound();
  $this->putJson("/api/v1/tasks/{$task->id}",['title'=>'Stolen'])->assertNotFound();
 }
 public function test_overdue_is_computed_not_persisted():void {
  [$u,$h]=$this->actor();$task=Task::create(['household_id'=>$h->id,'user_id'=>$u->id,'title'=>'Late','status'=>'pending','due_at'=>now()->subDay()]);
  $this->getJson('/api/v1/tasks?view=overdue')->assertOk()->assertJsonPath('data.tasks.data.0.effective_status','overdue');
  $this->assertSame('pending',$task->fresh()->status);
 }
 public function test_recurrence_generates_next_instance_exactly_once():void {
  $this->actor();$start=now()->addDay()->startOfMinute();
  $created=$this->postJson('/api/v1/tasks',['title'=>'Daily check','due_at'=>$start->toIso8601String(),'recurrence'=>['frequency'=>'daily','interval'=>1]])->assertCreated();
  $id=$created->json('data.task.recurring_rule_id');$this->assertNotNull($id);
  $this->travelTo($start->copy()->addDay()->addMinute());
  app(RecurrenceService::class)->generateDue();app(RecurrenceService::class)->generateDue();
  $this->assertEquals(2,Task::where('recurring_rule_id',$id)->count());
 }
 public function test_unreviewed_due_date_does_not_create_suggestion():void {
  [$u,$h]=$this->actor();[$doc,$ex]=$this->doc($u,$h);
  ExtractedField::where('document_extraction_id',$ex->id)->where('field_name','due_date')->update(['review_status'=>'needs_review']);
  $this->getJson("/api/v1/documents/{$doc->id}/obligation-suggestions")->assertOk()->assertJsonCount(0,'data.suggestions');
  $this->assertDatabaseCount('tasks',0);
 }
 public function test_reminder_snooze_is_limited_by_due_date():void {
  $this->actor();$id=$this->postJson('/api/v1/tasks',['title'=>'Reminder test','due_at'=>now()->addDays(10)->toIso8601String()])->json('data.task.id');
  $rid=Task::findOrFail($id)->reminders()->firstOrFail()->id;
  $this->postJson("/api/v1/tasks/{$id}/reminders/{$rid}/snooze",['until'=>now()->addDays(11)->toIso8601String()])->assertUnprocessable();
  $this->postJson("/api/v1/tasks/{$id}/reminders/{$rid}/snooze",['until'=>now()->addDay()->toIso8601String()])->assertOk();
 }
 public function test_dependency_cycle_is_rejected():void {
  $this->actor();$a=$this->postJson('/api/v1/tasks',['title'=>'A'])->json('data.task.id');$b=$this->postJson('/api/v1/tasks',['title'=>'B'])->json('data.task.id');
  $this->postJson("/api/v1/tasks/{$a}/dependencies",['depends_on_task_id'=>$b])->assertOk();
  $this->postJson("/api/v1/tasks/{$b}/dependencies",['depends_on_task_id'=>$a])->assertUnprocessable();
 }
}
