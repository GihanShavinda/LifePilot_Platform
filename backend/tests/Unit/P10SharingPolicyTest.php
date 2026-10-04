<?php

namespace Tests\Unit;

use App\Domain\Collaboration\Enums\SharingScope;
use App\Domain\Collaboration\Services\SharedResourceService;
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\{Household, HouseholdMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P10SharingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_is_default_and_household_scope_is_explicit(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $household = Household::create(['name' => 'Sharing Policy']);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $owner->id, 'role' => 'owner']);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $member->id, 'role' => 'member']);
        $document = Document::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'title' => 'Private',
            'original_filename' => 'x.txt',
            'mime_type' => 'text/plain',
            'size' => 1,
            'storage_disk' => 'documents',
            'storage_path' => 'x.txt',
            'checksum' => str_repeat('a', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);

        $sharing = app(SharedResourceService::class);
        $this->assertSame(SharingScope::Private, $sharing->scope($document));
        $this->assertTrue($sharing->canRead($owner, $document));
        $this->assertFalse($sharing->canRead($member, $document));

        $sharing->share($owner, $document, SharingScope::Household);
        $this->assertTrue($sharing->canRead($member, $document));

        $sharing->share($owner, $document, SharingScope::Private);
        $this->assertFalse($sharing->canRead($member, $document));
    }
}
