<?php

namespace Tests\Feature;

use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Completion\Models\EvaluationCase;
use App\Domain\Documents\Models\Document;
use App\Domain\Users\Models\{Household, HouseholdMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P12CompletionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $email = 'p12@example.com'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $household = Household::create(['name' => 'P12 Home']);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return [$user, $household];
    }

    public function test_completion_dashboards_load_for_authenticated_user(): void
    {
        $this->actor();

        $this->getJson('/api/v1/completion/dashboard')->assertOk()
            ->assertJsonStructure(['data' => ['main', 'documents', 'finance', 'ai']]);
        $this->getJson('/api/v1/completion/dashboard/main')->assertOk();
        $this->getJson('/api/v1/completion/dashboard/documents')->assertOk();
        $this->getJson('/api/v1/completion/dashboard/finance?months=3')->assertOk();
        $this->getJson('/api/v1/completion/dashboard/ai')->assertOk();
    }

    public function test_private_document_from_another_member_is_not_counted(): void
    {
        [$owner, $household] = $this->actor('owner-p12@example.com');
        $member = User::factory()->create(['email' => 'member-p12@example.com']);
        HouseholdMember::create(['household_id' => $household->id, 'user_id' => $member->id, 'role' => 'member']);

        Document::create([
            'user_id' => $owner->id,
            'household_id' => $household->id,
            'title' => 'Owner private',
            'original_filename' => 'owner-private.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'storage_disk' => 'documents',
            'storage_path' => 'owner-private.txt',
            'checksum' => str_repeat('a', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);
        Document::create([
            'user_id' => $member->id,
            'household_id' => $household->id,
            'title' => 'Member private',
            'original_filename' => 'member-private.txt',
            'mime_type' => 'text/plain',
            'size' => 10,
            'storage_disk' => 'documents',
            'storage_path' => 'member-private.txt',
            'checksum' => str_repeat('b', 64),
            'status' => 'active',
            'processing_status' => 'ready',
        ]);

        Sanctum::actingAs($member);
        $this->getJson('/api/v1/completion/dashboard/documents')
            ->assertOk()
            ->assertJsonPath('data.total_documents', 1);
    }

    public function test_evaluation_does_not_invent_metrics_without_ground_truth(): void
    {
        $this->actor();

        $run = $this->postJson('/api/v1/evaluation/run')->assertCreated()->json('data.run');
        $metric = collect($run['metrics'])->first(fn (array $row) => $row['variant'] === 'full_agentic' && $row['metric_key'] === 'extraction_accuracy');

        $this->assertNull($metric['value']);
        $this->assertSame('not_measured', $metric['status']);
        $this->assertSame(0, $metric['sample_size']);
    }

    public function test_labeled_cases_produce_real_metric_values(): void
    {
        [$user, $household] = $this->actor();

        foreach (['pass', 'pass', 'pass', 'fail'] as $index => $outcome) {
            EvaluationCase::create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'variant' => 'rules_extraction',
                'metric_key' => 'extraction_accuracy',
                'case_key' => 'case-'.$index,
                'outcome' => $outcome,
            ]);
        }

        $run = $this->postJson('/api/v1/evaluation/run')->assertCreated()->json('data.run');
        $metric = collect($run['metrics'])->first(fn (array $row) => $row['variant'] === 'rules_extraction' && $row['metric_key'] === 'extraction_accuracy');

        $this->assertSame(75.0, (float) $metric['value']);
        $this->assertSame('measured_labeled', $metric['status']);
        $this->assertSame(4, $metric['sample_size']);
    }

    public function test_ai_recommendation_dashboard_preserves_evidence(): void
    {
        [$user, $household] = $this->actor();
        AnalyticsInsight::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'insight_key' => 'p12-test',
            'type' => 'test',
            'severity' => 'info',
            'title' => 'Grounded recommendation',
            'message' => 'Review task 10.',
            'metrics' => [],
            'evidence_refs' => ['task:10'],
            'model_version' => 'p11-analytics-v1.0.0',
            'feature_version' => 'p11-features-v1',
            'generated_at' => now(),
        ]);

        $this->getJson('/api/v1/completion/dashboard/ai')->assertOk()
            ->assertJsonPath('data.recommendations.0.evidence_refs.0', 'task:10');
    }

    public function test_final_report_exports_json_csv_and_pdf(): void
    {
        $this->actor();
        $this->postJson('/api/v1/evaluation/run')->assertCreated();

        $this->get('/api/v1/evaluation/export/json')->assertOk()->assertHeader('content-type', 'application/json');
        $this->get('/api/v1/evaluation/export/csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $pdf = $this->get('/api/v1/evaluation/export/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-1.4', $pdf->getContent());
    }

    public function test_security_review_marks_manual_controls_without_claiming_automation(): void
    {
        $this->actor();
        $checks = $this->getJson('/api/v1/evaluation/security-review')->assertOk()->json('data.checks');
        $secret = collect($checks)->firstWhere('check_key', 'secret_management');
        $this->assertSame('manual_review', $secret['status']);
    }
}
