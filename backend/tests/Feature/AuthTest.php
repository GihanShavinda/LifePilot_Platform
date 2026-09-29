<?php
namespace Tests\Feature;use App\Domain\Audit\Models\AuditLog;use App\Domain\Users\Models\User;use Illuminate\Foundation\Testing\RefreshDatabase;use Tests\TestCase;
class AuthTest extends TestCase{use RefreshDatabase;
 public function test_registration_creates_user_household_preferences_and_audit():void{$r=$this->postJson('/api/v1/auth/register',['name'=>'Ava','email'=>'ava@example.com','password'=>'StrongPass123','password_confirmation'=>'StrongPass123','timezone'=>'Asia/Colombo']);$r->assertCreated()->assertJsonPath('success',true);$this->assertAuthenticated();$this->assertDatabaseHas('household_members',['user_id'=>1,'role'=>'owner']);$this->assertDatabaseHas('notification_preferences',['user_id'=>1]);$this->assertDatabaseHas('audit_logs',['event'=>'auth.registered']);}
 public function test_login_and_logout():void{$u=User::factory()->create(['password'=>'StrongPass123']);$this->postJson('/api/v1/auth/login',['email'=>$u->email,'password'=>'StrongPass123'])->assertOk();$this->assertAuthenticatedAs($u);$this->postJson('/api/v1/auth/logout')->assertOk();$this->assertGuest();}
 public function test_protected_route_rejects_guest():void{$this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('error.code','UNAUTHENTICATED');}
 public function test_registration_validation_is_standardized():void{$this->postJson('/api/v1/auth/register',[])->assertStatus(422)->assertJsonPath('error.code','VALIDATION_ERROR');}
}
