<?php

namespace Tests\Feature;

use App\Domain\Auth\Enums\HouseholdRole;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;
    private function member(HouseholdRole $role): User
    {
        $u = User::factory()->create();
        $h = Household::create(['name' => 'Home']);
        HouseholdMember::create(['household_id' => $h->id, 'user_id' => $u->id, 'role' => $role]);
        return $u;
    }
    public function test_owner_can_access_owner_route(): void
    {
        $u = $this->member(HouseholdRole::Owner);
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/owner-check')->assertOk();
    }
    public function test_viewer_cannot_access_owner_route(): void
    {
        $u = $this->member(HouseholdRole::Viewer);
        Sanctum::actingAs($u);
        $this->getJson('/api/v1/owner-check')->assertForbidden();
    }
}
