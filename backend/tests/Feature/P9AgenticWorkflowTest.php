<?php

namespace Tests\Feature;

use App\Domain\Actions\Models\{ActionApproval, ActionExecution, ActionPlan, ActionResult};
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\{Household, HouseholdMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P9AgenticWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): array
    {
        $user = User::factory()->create();
        $household = Household::create(['name' => 'P9 Household']);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        Sanctum::actingAs($user);
        return [$user, $household];
    }

    private function createTaskPlan(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'request' => 'Create a safe draft task',
            'origin' => 'user',
            'steps' => [[
                'action_type' => 'create_task',
                'title' => 'Create follow-up task',
                'payload' => [
                    'title' => 'Review electricity bill',
                    'priority' => 'medium',
                ],
            ]],
        ], $overrides);

        return $this->postJson('/api/v1/action-plans', $payload)
            ->assertCreated()
            ->json('data.plan');
    }

    public function test_safe_plan_is_previewed_before_execution(): void
    {
        $this->actor();
        $plan = $this->createTaskPlan();

        $this->assertSame('preview', $plan['status']);
        $this->assertSame('low', $plan['overall_risk']);
        $this->assertSame('create_task', $plan['steps'][0]['action_type']);
        $this->assertTrue($plan['steps'][0]['requires_approval']);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_unsupported_action_is_rejected(): void
    {
        $this->actor();

        $this->postJson('/api/v1/action-plans', [
            'request' => 'Transfer money',
            'steps' => [[
                'action_type' => 'perform_financial_action',
                'payload' => ['amount' => '1000.00'],
            ]],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('action_plans', 0);
    }

    public function test_assistant_plan_with_missing_evidence_is_rejected(): void
    {
        $this->actor();

        $this->postJson('/api/v1/action-plans', [
            'request' => 'Assistant recommends a task',
            'origin' => 'assistant',
            'evidence_refs' => [],
            'steps' => [[
                'action_type' => 'create_task',
                'payload' => ['title' => 'Unsupported assistant task'],
            ]],
        ])->assertUnprocessable();
    }

    public function test_approval_is_required_before_execution(): void
    {
        $this->actor();
        $plan = $this->createTaskPlan();

        $this->postJson("/api/v1/action-plans/{$plan['id']}/execute")
            ->assertUnprocessable();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_high_risk_action_requires_explicit_acknowledgement(): void
    {
        $this->actor();

        $plan = $this->postJson('/api/v1/action-plans', [
            'request' => 'Prepare an external calendar integration request',
            'steps' => [[
                'action_type' => 'request_integration_action',
                'payload' => [
                    'integration' => 'google_calendar',
                    'operation' => 'update_external_event',
                    'parameters' => ['external_event_id' => 'abc'],
                ],
            ]],
        ])->assertCreated()->json('data.plan');

        $this->assertSame('high', $plan['overall_risk']);

        $this->postJson("/api/v1/action-plans/{$plan['id']}/approve", [
            'step_ids' => [$plan['steps'][0]['id']],
            'high_risk_ack' => false,
        ])->assertUnprocessable();

        $this->postJson("/api/v1/action-plans/{$plan['id']}/approve", [
            'step_ids' => [$plan['steps'][0]['id']],
            'high_risk_ack' => true,
        ])->assertOk();
    }

    public function test_duplicate_execution_is_idempotent(): void
    {
        $this->actor();
        $plan = $this->createTaskPlan();
        $stepId = $plan['steps'][0]['id'];

        $this->postJson("/api/v1/action-plans/{$plan['id']}/approve", [
            'step_ids' => [$stepId],
        ])->assertOk();

        $this->postJson("/api/v1/action-plans/{$plan['id']}/execute")
            ->assertOk();
        $this->postJson("/api/v1/action-plans/{$plan['id']}/execute")
            ->assertOk();

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseCount('action_executions', 1);
    }

    public function test_failed_action_rolls_back_prior_database_side_effects(): void
    {
        $this->actor();

        $plan = $this->postJson('/api/v1/action-plans', [
            'request' => 'Create task then invalid reminder',
            'steps' => [
                [
                    'action_type' => 'create_task',
                    'payload' => ['title' => 'Must roll back'],
                ],
                [
                    'action_type' => 'create_reminder',
                    'payload' => [
                        'task_id' => 999999,
                        'remind_at' => now()->addDay()->toISOString(),
                    ],
                ],
            ],
        ])->assertCreated()->json('data.plan');

        $this->postJson("/api/v1/action-plans/{$plan['id']}/approve", [
            'step_ids' => array_column($plan['steps'], 'id'),
        ])->assertOk();

        $response = $this->postJson("/api/v1/action-plans/{$plan['id']}/execute")
            ->assertOk()
            ->json('data.plan');

        $this->assertSame('failed', $response['status']);
        $this->assertDatabaseCount('tasks', 0);
        $this->assertDatabaseHas('action_executions', ['status' => 'rolled_back']);
        $this->assertDatabaseHas('action_executions', ['status' => 'failed']);
    }

    public function test_audit_is_complete_after_successful_execution(): void
    {
        $this->actor();
        $plan = $this->createTaskPlan();

        $this->postJson("/api/v1/action-plans/{$plan['id']}/approve", [
            'step_ids' => [$plan['steps'][0]['id']],
            'comment' => 'Reviewed and approved.',
        ])->assertOk();

        $this->postJson("/api/v1/action-plans/{$plan['id']}/execute")
            ->assertOk();

        $audit = $this->getJson("/api/v1/action-plans/{$plan['id']}/audit")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $audit['approvals']);
        $this->assertCount(1, $audit['executions']);
        $this->assertNotNull($audit['executions'][0]['result']);
        $this->assertSame('completed', $audit['plan']['status']);

        $this->assertSame(1, ActionApproval::count());
        $this->assertSame(1, ActionExecution::count());
        $this->assertSame(1, ActionResult::count());
        $this->assertSame(1, ActionPlan::count());
    }
}
