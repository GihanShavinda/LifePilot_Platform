<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Models\ExtractedField;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentExtractionReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_accept_reject_and_edit_grounded_fields(): void
    {
        [$user, $document, $extraction] = $this->context();

        $accept = $this->field(
            $extraction,
            'issuer',
            'Example Utility'
        );

        $edit = $this->field(
            $extraction,
            'amount',
            '100.00'
        );

        $reject = $this->field(
            $extraction,
            'important_notes',
            'Old note'
        );

        $this->putJson(
            "/api/v1/documents/{$document->id}/intelligence/fields/{$accept->id}/review",
            ['decision' => 'accept']
        )->assertOk()
            ->assertJsonPath(
                'data.field.review_status',
                'accepted'
            );

        $this->putJson(
            "/api/v1/documents/{$document->id}/intelligence/fields/{$edit->id}/review",
            [
                'decision' => 'edit',
                'value' => '125.00',
            ]
        )->assertOk()
            ->assertJsonPath(
                'data.field.review_status',
                'edited'
            );

        $this->putJson(
            "/api/v1/documents/{$document->id}/intelligence/fields/{$reject->id}/review",
            ['decision' => 'reject']
        )->assertOk()
            ->assertJsonPath(
                'data.field.review_status',
                'rejected'
            );

        $this->assertDatabaseCount(
            'extraction_reviews',
            3
        );
    }

    public function test_other_household_cannot_review_extraction(): void
    {
        [$user, $document, $extraction] = $this->context();

        $field = $this->field(
            $extraction,
            'issuer',
            'Private Issuer'
        );

        $other = User::factory()->create();
        $otherHousehold = Household::create([
            'name' => 'Other Household',
        ]);

        HouseholdMember::create([
            'household_id' => $otherHousehold->id,
            'user_id' => $other->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($other);

        $this->putJson(
            "/api/v1/documents/{$document->id}/intelligence/fields/{$field->id}/review",
            ['decision' => 'accept']
        )->assertForbidden();
    }

    private function context(): array
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'P3 Household',
        ]);

        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($user);

        $document = Document::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'title' => 'Utility Bill',
            'original_filename' => 'bill.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'bill.txt',
            'checksum' => str_repeat('a', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'version_number' => 1,
            'original_filename' => 'bill.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'bill.txt',
            'checksum' => str_repeat('a', 64),
        ]);

        $extraction = DocumentExtraction::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'version_number' => 1,
            'status' => 'needs_review',
            'source_text_hash' => str_repeat('b', 64),
            'source_text' => 'Example Utility Amount Due 100.00',
            'extractor_version' => 'test',
            'started_at' => now(),
        ]);

        return [$user, $document, $extraction];
    }

    private function field(
        DocumentExtraction $extraction,
        string $name,
        mixed $value
    ): ExtractedField {
        return ExtractedField::create([
            'document_extraction_id' => $extraction->id,
            'field_name' => $name,
            'value' => $value,
            'normalized_value' => $value,
            'confidence' => 0.80,
            'page' => 1,
            'evidence_text' => (string) $value,
            'extractor_version' => 'test',
            'review_status' => 'needs_review',
            'source' => 'deterministic',
            'fingerprint' => hash(
                'sha256',
                $name.'|'.json_encode($value)
            ),
        ]);
    }
}
