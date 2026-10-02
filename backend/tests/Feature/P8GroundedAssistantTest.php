<?php

namespace Tests\Feature;

use App\Domain\Assistant\Contracts\GroundedLlm;
use App\Domain\Assistant\Models\RetrievalTrace;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Documents\Models\DocumentVersion;
use App\Domain\Documents\Models\ExtractedField;
use App\Domain\Finance\Models\Expense;
use App\Domain\Obligations\Models\Obligation;
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\Household;
use App\Domain\Users\Models\HouseholdMember;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeGroundedLlm;
use Tests\TestCase;

class P8GroundedAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role = 'owner'): array
    {
        $user = User::factory()->create();

        $household = Household::create([
            'name' => 'AI household',
        ]);

        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => $role,
        ]);

        Sanctum::actingAs($user);

        return [$user, $household];
    }

    private function assistantSession(): int
    {
        return $this
            ->postJson('/api/v1/assistant/sessions')
            ->assertCreated()
            ->json('data.session.id');
    }

    public function test_grounded_answer_persists_claims_citations_trace_and_model_metadata(): void
    {
        [$user, $household] = $this->actor();

        $obligation = Obligation::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'type' => 'payment',
            'title' => 'Insurance bill',
            'amount' => '2500.00',
            'currency' => 'LKR',
            'due_at' => now()->addDays(3),
            'status' => 'approved',
            'dedupe_key' => hash('sha256', 'p8-grounded'),
        ]);

        $key = 'obligation:' . $obligation->id;

        $this->app->instance(
            GroundedLlm::class,
            new FakeGroundedLlm([
                'answer' => 'Insurance bill is due for LKR 2500.00.',
                'claims' => [
                    [
                        'text' => 'Insurance bill is LKR 2500.00.',
                        'citations' => [$key],
                        'factual_values' => [
                            '2500.00',
                            'LKR',
                        ],
                    ],
                ],
            ])
        );

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' => 'What bills do I need to pay this week?',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertSame(
            'validated',
            $message['grounding_status']
        );

        $this->assertContains(
            $key,
            $message['citations']
        );

        $this->assertSame(
            'fake',
            $message['model_provider']
        );

        $this->assertSame(
            'fake-grounded',
            $message['model_name'] ?? $message['model'] ?? null
        );

        $this->assertSame(
            'test',
            $message['model_version']
        );

        $this->assertDatabaseCount(
            'retrieval_traces',
            1
        );

        $trace = RetrievalTrace::first();

        $this->assertNotNull($trace);

        $this->assertSame(
            $household->id,
            $trace->household_id
        );

        $this->assertGreaterThan(
            0,
            $trace->result_count
        );
    }

    public function test_missing_evidence_returns_deterministic_no_evidence_response(): void
    {
        $this->actor();

        $this->app->instance(
            GroundedLlm::class,
            new FakeGroundedLlm([
                'answer' => 'Made up',
                'claims' => [
                    [
                        'text' => 'Made up',
                        'citations' => [
                            'expense:999',
                        ],
                        'factual_values' => [
                            '999',
                        ],
                    ],
                ],
            ])
        );

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'When does my nonexistent yacht warranty expire?',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertSame(
            'no_evidence',
            $message['grounding_status']
        );

        $this->assertSame(
            [],
            $message['citations']
        );

        $this->assertStringContainsString(
            'could not find',
            strtolower($message['content'])
        );
    }

    public function test_unauthorized_household_records_are_never_retrieved(): void
    {
        [$user, $household] = $this->actor();

        $other = User::factory()->create();

        $otherHousehold = Household::create([
            'name' => 'Other',
        ]);

        HouseholdMember::create([
            'household_id' => $otherHousehold->id,
            'user_id' => $other->id,
            'role' => 'owner',
        ]);

        Expense::create([
            'household_id' => $otherHousehold->id,
            'user_id' => $other->id,
            'title' => 'Secret purchase',
            'amount' => '9999.00',
            'currency' => 'USD',
            'expense_date' => today(),
            'source' => 'manual',
        ]);

        $id = $this->assistantSession();

        $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'How much was the secret purchase?',
                ]
            )
            ->assertCreated();

        $trace = RetrievalTrace::latest('id')->first();

        $this->assertNotNull($trace);

        $json = json_encode($trace->evidence);

        $this->assertStringNotContainsString(
            'Secret purchase',
            $json
        );

        $this->assertStringNotContainsString(
            '9999.00',
            $json
        );

        $this->assertSame(
            $household->id,
            $trace->household_id
        );
    }

    public function test_conflicting_accepted_document_values_can_be_reported_only_with_both_sources(): void
    {
        [$user, $household] = $this->actor();

        $document = Document::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'title' => 'Conflicting renewal letters',
            'original_filename' => 'renewal.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'renewal.txt',
            'checksum' => str_repeat('a', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);

        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'uploaded_by' => $user->id,
            'version_number' => 1,
            'original_filename' => 'renewal.txt',
            'mime_type' => 'text/plain',
            'size' => 100,
            'storage_disk' => 'documents',
            'storage_path' => 'renewal.txt',
            'checksum' => str_repeat('b', 64),
        ]);

        $extraction = DocumentExtraction::create([
            'document_id' => $document->id,
            'document_version_id' => $version->id,
            'version_number' => 1,
            'status' => 'needs_review',
            'extractor_version' => 'test',
            'source_text' =>
                'Due Date: 2026-10-10. ' .
                'Another letter says Due Date: 2026-10-12.',
        ]);

        $first = ExtractedField::create([
            'document_extraction_id' => $extraction->id,
            'field_name' => 'due_date',
            'value' => '2026-10-10',
            'normalized_value' => '2026-10-10',
            'confidence' => 0.99,
            'page' => 1,
            'evidence_text' => 'Due Date: 2026-10-10',
            'extractor_version' => 'test',
            'review_status' => 'accepted',
            'source' => 'deterministic',
            'fingerprint' => hash(
                'sha256',
                'p8-conflict-a'
            ),
        ]);

        $second = ExtractedField::create([
            'document_extraction_id' => $extraction->id,
            'field_name' => 'due_date',
            'value' => '2026-10-12',
            'normalized_value' => '2026-10-12',
            'confidence' => 0.99,
            'page' => 1,
            'evidence_text' => 'Due Date: 2026-10-12',
            'extractor_version' => 'test',
            'review_status' => 'accepted',
            'source' => 'deterministic',
            'fingerprint' => hash(
                'sha256',
                'p8-conflict-b'
            ),
        ]);

        $firstKey =
            'extraction_field:' . $first->id;

        $secondKey =
            'extraction_field:' . $second->id;

        $this->app->instance(
            GroundedLlm::class,
            new FakeGroundedLlm([
                'answer' =>
                    'The accepted evidence conflicts: ' .
                    'one date is 2026-10-10 and another is 2026-10-12.',
                'claims' => [
                    [
                        'text' =>
                            'Two accepted dates conflict.',
                        'citations' => [
                            $firstKey,
                            $secondKey,
                        ],
                        'factual_values' => [
                            '2026-10-10',
                            '2026-10-12',
                        ],
                    ],
                ],
            ])
        );

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'When is this renewal due?',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertSame(
            'validated',
            $message['grounding_status']
        );

        $this->assertCount(
            2,
            $message['citations']
        );

        $this->assertContains(
            $firstKey,
            $message['citations']
        );

        $this->assertContains(
            $secondKey,
            $message['citations']
        );
    }

    public function test_hallucinated_amount_from_model_is_rejected_and_fallback_is_used(): void
    {
        [$user, $household] = $this->actor();

        $expense = Expense::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'title' => 'Groceries',
            'amount' => '100.00',
            'currency' => 'LKR',
            'expense_date' => today(),
            'source' => 'manual',
        ]);

        $this->app->instance(
            GroundedLlm::class,
            new FakeGroundedLlm([
                'answer' => 'You spent LKR 999.00.',
                'claims' => [
                    [
                        'text' =>
                            'You spent LKR 999.00.',
                        'citations' => [
                            'expense:' . $expense->id,
                        ],
                        'factual_values' => [
                            '999.00',
                            'LKR',
                        ],
                    ],
                ],
            ])
        );

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'How much did I spend?',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertSame(
            'deterministic_fallback',
            $message['grounding_status']
        );

        $this->assertStringNotContainsString(
            '999.00',
            $message['content']
        );

        $this->assertStringContainsString(
            '100',
            $message['content']
        );
    }

    public function test_fake_deadline_injection_from_model_is_rejected(): void
    {
        [$user, $household] = $this->actor();

        $task = Task::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'title' => 'Submit form',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => '2026-10-10 10:00:00',
        ]);

        $this->app->instance(
            GroundedLlm::class,
            new FakeGroundedLlm([
                'answer' =>
                    'The deadline is 2026-10-30.',
                'claims' => [
                    [
                        'text' =>
                            'Deadline is 2026-10-30.',
                        'citations' => [
                            'task:' . $task->id,
                        ],
                        'factual_values' => [
                            '2026-10-30',
                        ],
                    ],
                ],
            ])
        );

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'What should I handle before the end of this month?',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertSame(
            'deterministic_fallback',
            $message['grounding_status']
        );

        $this->assertStringNotContainsString(
            '2026-10-30',
            $message['content']
        );

        $this->assertStringContainsString(
            '2026-10-10',
            $message['content']
        );
    }

    public function test_draft_task_is_a_proposal_and_never_creates_a_task(): void
    {
        [$user, $household] = $this->actor();

        Obligation::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'type' => 'renewal',
            'title' => 'Renew insurance',
            'due_at' => now()->addDays(5),
            'status' => 'approved',
            'dedupe_key' =>
                hash('sha256', 'p8-draft'),
        ]);

        $id = $this->assistantSession();

        $message = $this
            ->postJson(
                "/api/v1/assistant/sessions/{$id}/messages",
                [
                    'question' =>
                        'Draft a task for my insurance renewal',
                ]
            )
            ->assertCreated()
            ->json('data.message');

        $this->assertDatabaseCount(
            'tasks',
            0
        );

        $this->assertSame(
            'task',
            $message['metadata']['draft']['type']
        );

        $this->assertNotEmpty(
            $message['metadata']['draft']['citations']
        );
    }
}