<?php

namespace Tests\Feature;

use App\Domain\Assistant\Enums\AssistantIntent;
use App\Domain\Assistant\Services\EvidenceBundleBuilder;
use App\Domain\Collaboration\Models\{Assignment, HouseholdInvitation, SharedResource};
use App\Domain\Documents\Models\{Document, DocumentExtraction, DocumentVersion, ExtractedField};
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\{Household, HouseholdMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P10HouseholdCollaborationTest extends TestCase
{
    use RefreshDatabase;

    private function householdWith(User $user, string $role = 'owner'): Household
    {
        $household = Household::create(['name' => 'P10 Household']);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => $role]);
        return $household;
    }

    private function addMember(Household $household, User $user, string $role): HouseholdMember
    {
        return HouseholdMember::create(['household_id' => $household->id, 'user_id' => $user->id, 'role' => $role]);
    }

    private function document(User $owner, Household $household, string $title): Document
    {
        return Document::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'title' => $title,
            'original_filename' => 'private.txt',
            'mime_type' => 'text/plain',
            'size' => 20,
            'storage_disk' => 'documents',
            'storage_path' => 'tests/private.txt',
            'checksum' => hash('sha256', $title),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);
    }

    public function test_owner_can_invite_and_matching_user_can_accept(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $invitee = User::factory()->create(['email' => 'member@example.com']);
        $household = $this->householdWith($owner);

        Sanctum::actingAs($owner);
        $payload = $this->postJson('/api/v1/households/current/invitations', [
            'email' => $invitee->email,
            'role' => 'member',
        ])->assertCreated()->json('data');

        $this->assertDatabaseHas('household_invitations', [
            'household_id' => $household->id,
            'email' => $invitee->email,
            'status' => 'pending',
        ]);
        $this->assertNotEmpty($payload['accept_token']);

        Sanctum::actingAs($invitee);
        $this->postJson('/api/v1/household-invitations/'.$payload['accept_token'].'/accept')
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $this->assertDatabaseHas('household_members', [
            'household_id' => $household->id,
            'user_id' => $invitee->id,
            'role' => 'member',
        ]);
        $this->assertSame('accepted', HouseholdInvitation::first()->status->value);
    }

    public function test_role_permissions_protect_member_management(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $admin = User::factory()->create();
        $household = $this->householdWith($owner);
        $this->addMember($household, $viewer, 'viewer');
        $this->addMember($household, $admin, 'admin');

        Sanctum::actingAs($viewer);
        $this->postJson('/api/v1/households/current/invitations', [
            'email' => 'blocked@example.com', 'role' => 'member',
        ])->assertForbidden();

        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/households/current/invitations', [
            'email' => 'allowed@example.com', 'role' => 'viewer',
        ])->assertCreated();
    }

    public function test_private_document_is_owner_only_until_explicitly_shared(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = $this->householdWith($owner);
        $this->addMember($household, $member, 'member');
        $document = $this->document($owner, $household, 'Owner private insurance');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/documents/'.$document->id)->assertForbidden();

        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/shared-resources/document/'.$document->id, ['scope' => 'household'])
            ->assertOk()
            ->assertJsonPath('data.sharing.scope', 'household');

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/documents/'.$document->id)->assertOk();

        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/shared-resources/document/'.$document->id, ['scope' => 'private'])
            ->assertOk();

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/documents/'.$document->id)->assertForbidden();
    }

    public function test_ai_evidence_bundle_never_exposes_another_members_private_document(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = $this->householdWith($owner);
        $this->addMember($household, $member, 'member');
        $document = $this->document($owner, $household, 'Secret electricity bill');

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'uploaded_by' => $owner->id,
            'version_number' => 1,
            'original_filename' => 'private.txt',
            'mime_type' => 'text/plain',
            'size' => 20,
            'storage_disk' => 'documents',
            'storage_path' => 'tests/private.txt',
            'checksum' => hash('sha256', 'private-version'),
        ]);
        $extraction = DocumentExtraction::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'version_number' => 1,
            'status' => 'completed',
            'source_text_hash' => hash('sha256', 'Electricity bill amount LKR 99999'),
            'source_text' => 'Electricity bill amount LKR 99999',
            'document_type' => 'bill',
            'overall_confidence' => 0.99,
            'extractor_version' => 'p10-test',
        ]);
        $field = ExtractedField::create([
            'document_extraction_id' => $extraction->id,
            'field_name' => 'amount',
            'value' => ['value' => '99999.00'],
            'normalized_value' => ['value' => '99999.00'],
            'confidence' => 0.99,
            'page' => 1,
            'evidence_text' => 'LKR 99999',
            'extractor_version' => 'p10-test',
            'review_status' => 'accepted',
            'source' => 'deterministic',
            'fingerprint' => hash('sha256', 'amount-private'),
        ]);

        $bundle = app(EvidenceBundleBuilder::class)->build(
            $member,
            $household->id,
            'electricity bill amount',
            AssistantIntent::Search,
        );

        $keys = array_column($bundle, 'key');
        $this->assertNotContains('extraction_field:'.$field->id, $keys);
        foreach ($bundle as $evidence) {
            $this->assertNotSame($document->id, data_get($evidence, 'source.document_id'));
        }

        app(\App\Domain\Collaboration\Services\SharedResourceService::class)
            ->share($owner, $document, \App\Domain\Collaboration\Enums\SharingScope::Household);

        $sharedBundle = app(EvidenceBundleBuilder::class)->build(
            $member,
            $household->id,
            'electricity bill amount',
            AssistantIntent::Search,
        );
        $this->assertContains('extraction_field:'.$field->id, array_column($sharedBundle, 'key'));
    }

    public function test_task_assignment_explicitly_shares_task_and_creates_assignment(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = $this->householdWith($owner);
        $this->addMember($household, $member, 'member');
        $task = Task::create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'title' => 'Pay electricity bill',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/tasks/'.$task->id.'/assign', [
            'assignee_user_id' => $member->id,
            'note' => 'Please handle this before Friday.',
        ])->assertCreated()
            ->assertJsonPath('data.assignment.assignee_user_id', $member->id);

        $this->assertDatabaseHas('assignments', [
            'assignable_type' => 'task',
            'assignable_id' => $task->id,
            'assignee_user_id' => $member->id,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('shared_resources', [
            'resource_type' => 'task',
            'resource_id' => $task->id,
            'scope' => 'household',
        ]);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/tasks/'.$task->id)->assertOk();
    }

    public function test_member_removal_revokes_household_access_and_removes_assignments(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = $this->householdWith($owner);
        $membership = $this->addMember($household, $member, 'member');
        $document = $this->document($owner, $household, 'Shared family policy');
        SharedResource::create([
            'household_id' => $household->id,
            'owner_user_id' => $owner->id,
            'shared_by_user_id' => $owner->id,
            'resource_type' => 'document',
            'resource_id' => $document->id,
            'scope' => 'household',
            'shared_at' => now(),
        ]);
        $task = Task::create([
            'household_id' => $household->id,
            'user_id' => $owner->id,
            'title' => 'Shared assignment',
            'priority' => 'medium',
            'status' => 'pending',
        ]);
        Assignment::create([
            'household_id' => $household->id,
            'assignable_type' => 'task',
            'assignable_id' => $task->id,
            'assignee_user_id' => $member->id,
            'assigned_by_user_id' => $owner->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/documents/'.$document->id)->assertOk();

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/v1/households/current/members/'.$membership->id)
            ->assertOk()
            ->assertJsonPath('data.removed', true);
        $this->assertDatabaseMissing('household_members', ['id' => $membership->id]);
        $this->assertDatabaseMissing('assignments', ['assignee_user_id' => $member->id]);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/documents/'.$document->id)->assertForbidden();
    }
}
