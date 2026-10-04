<?php

namespace Tests\Feature;

use App\Domain\Analytics\Models\{AnalyticsInsight, PredictionRecord};
use App\Domain\Analytics\Services\PredictionService;
use App\Domain\Collaboration\Models\SharedResource;
use App\Domain\Finance\Models\Expense;
use App\Domain\Obligations\Models\{Task, TaskChecklistItem};
use App\Domain\Users\Models\{Household, HouseholdMember, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class P11AnalyticsPredictionTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $email = 'analytics@example.com'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $household = Household::create(['name' => 'Analytics Home']);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        Sanctum::actingAs($user);

        return [$user, $household];
    }

    private function expense(User $user, Household $household, string $date, float $amount, string $currency = 'LKR', ?string $cycle = 'monthly'): Expense
    {
        return Expense::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'title' => 'Recurring '.$date,
            'amount' => $amount,
            'currency' => $currency,
            'expense_date' => $date,
            'source' => 'manual',
            'recurrence_cycle' => $cycle,
        ]);
    }

    public function test_insufficient_history_does_not_fabricate_forecast(): void
    {
        [$user, $household] = $this->actor();
        $this->expense($user, $household, now()->startOfMonth()->toDateString(), 1000);

        $response = $this->postJson('/api/v1/analytics/refresh')->assertOk();
        $forecast = collect($response->json('data.predictions'))
            ->firstWhere('prediction_type', 'recurring_expense_forecast');

        $this->assertSame('insufficient_history', $forecast['prediction']['status']);
        $this->assertSame(0, (int) $forecast['confidence']);
    }

    public function test_prediction_is_reproducible_for_same_inputs(): void
    {
        [$user, $household] = $this->actor();

        foreach ([3 => 1000, 2 => 1200, 1 => 1100, 0 => 1300] as $monthsAgo => $amount) {
            $this->expense(
                $user,
                $household,
                now()->startOfMonth()->subMonths($monthsAgo)->addDays(2)->toDateString(),
                $amount
            );
        }

        $first = $this->postJson('/api/v1/analytics/refresh')->assertOk()->json('data.predictions');
        $second = $this->postJson('/api/v1/analytics/refresh')->assertOk()->json('data.predictions');

        $a = collect($first)->firstWhere('prediction_type', 'recurring_expense_forecast');
        $b = collect($second)->firstWhere('prediction_type', 'recurring_expense_forecast');

        $this->assertSame($a['prediction'], $b['prediction']);
        $this->assertSame($a['input_fingerprint'], $b['input_fingerprint']);
        $this->assertSame(1, PredictionRecord::where('prediction_type', 'recurring_expense_forecast')->count());
    }

    public function test_risk_prediction_has_explanation_and_evidence(): void
    {
        [$user, $household] = $this->actor();

        $task = Task::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'title' => 'Urgent submission',
            'priority' => 'high',
            'status' => 'pending',
            'due_at' => now()->addDay(),
        ]);
        TaskChecklistItem::create([
            'task_id' => $task->id,
            'title' => 'Attach evidence',
            'sort_order' => 1,
            'is_completed' => false,
        ]);

        $this->postJson('/api/v1/analytics/refresh')->assertOk();

        $prediction = PredictionRecord::where('prediction_type', 'obligation_overdue_risk')->firstOrFail();
        $this->assertNotEmpty($prediction->explanation);
        $this->assertContains('task:'.$task->id, $prediction->evidence_refs);
        $this->assertSame('ok', $prediction->prediction['status']);
        $this->assertGreaterThan(0.5, $prediction->prediction['tasks'][0]['risk_probability']);
    }

    public function test_missing_subscription_data_is_reported_not_invented(): void
    {
        $this->actor();

        $this->postJson('/api/v1/analytics/refresh')->assertOk();
        $prediction = PredictionRecord::where('prediction_type', 'likely_next_subscription_charge')->firstOrFail();

        $this->assertSame('insufficient_history', $prediction->prediction['status']);
        $this->assertNull($prediction->prediction['charge']);
        $this->assertSame([], $prediction->evidence_refs);
    }

    public function test_model_and_feature_versions_are_persisted(): void
    {
        $this->actor();
        $this->postJson('/api/v1/analytics/refresh')->assertOk();

        $prediction = PredictionRecord::firstOrFail();
        $this->assertSame(PredictionService::MODEL_VERSION, $prediction->model_version);
        $this->assertSame(PredictionService::FEATURE_VERSION, $prediction->feature_version);
        $this->assertNotEmpty($prediction->generated_at);
        $this->assertIsArray($prediction->prediction);
    }

    public function test_false_spending_insight_is_not_created_without_baseline(): void
    {
        [$user, $household] = $this->actor();
        $this->expense($user, $household, now()->toDateString(), 5000, 'LKR', null);

        $this->postJson('/api/v1/analytics/refresh')->assertOk();

        $this->assertDatabaseMissing('analytics_insights', [
            'insight_key' => 'month_spending_change_LKR',
        ]);

        foreach (AnalyticsInsight::all() as $insight) {
            $this->assertNotEmpty($insight->evidence_refs);
        }
    }

    public function test_private_other_member_expense_is_not_included_in_analytics(): void
    {
        [$owner, $household] = $this->actor('owner@example.com');
        $member = User::factory()->create(['email' => 'member@example.com']);
        HouseholdMember::create([
            'household_id' => $household->id,
            'user_id' => $member->id,
            'role' => 'member',
        ]);

        $private = $this->expense($owner, $household, now()->toDateString(), 99999, 'LKR', null);
        $memberExpense = $this->expense($member, $household, now()->toDateString(), 250, 'LKR', null);

        Sanctum::actingAs($member);
        $data = $this->getJson('/api/v1/analytics/dashboard')->assertOk()->json('data.analytics.spending_trend');
        $current = collect($data)->first(fn (array $row) => $row['month'] === now()->format('Y-m') && $row['currency'] === 'LKR');

        $this->assertSame(250.0, (float) $current['total']);
        $this->assertFalse(SharedResource::query()->where('resource_type', 'expense')->where('resource_id', $private->id)->exists());
    }
}
