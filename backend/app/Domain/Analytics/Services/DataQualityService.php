<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\DataQualityCheck;
use App\Domain\Finance\Models\{Expense, Subscription};
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\User;
use Illuminate\Support\Collection;

class DataQualityService
{
    public function __construct(private readonly AnalyticsAccess $access)
    {
    }

    /** @return array<int,DataQualityCheck> */
    public function refresh(User $user): array
    {
        $householdId = $this->access->householdId($user);

        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', now()->startOfMonth()->subMonths(5)->toDateString())
            ->orderBy('expense_date')
            ->get();

        $tasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->get();

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return [
            $this->expenseHistoryCheck($user, $householdId, $expenses),
            $this->taskSampleCheck($user, $householdId, $tasks),
            $this->currencyCheck($user, $householdId, $expenses),
            $this->spendingDriftCheck($user, $householdId, $expenses),
            $this->subscriptionScheduleCheck($user, $householdId, $subscriptions),
        ];
    }

    private function expenseHistoryCheck(User $user, int $householdId, Collection $expenses): DataQualityCheck
    {
        $months = $expenses
            ->map(fn (Expense $expense) => $expense->expense_date?->format('Y-m'))
            ->filter()->unique()->count();

        return $this->persist(
            $user,
            $householdId,
            'expense_history_depth',
            $months >= 3 ? 'ok' : 'warning',
            'Expense history depth',
            $months >= 3
                ? "{$months} months of expense history are available for lightweight forecasting."
                : 'Fewer than three months of expense history are available, so trend forecasts may be withheld.',
            ['distinct_months' => $months, 'expense_count' => $expenses->count()],
            $expenses->take(100)->map(fn (Expense $expense) => 'expense:'.$expense->id)->all()
        );
    }

    private function taskSampleCheck(User $user, int $householdId, Collection $tasks): DataQualityCheck
    {
        $completed = $tasks->where('status', 'completed')->count();
        $status = $tasks->count() >= 5 ? 'ok' : 'info';

        return $this->persist(
            $user,
            $householdId,
            'task_sample_size',
            $status,
            'Task sample size',
            $tasks->count() >= 5
                ? 'There are enough task records to show completion and overdue-rate context.'
                : 'Task history is still small. Risk scores remain deterministic and should be interpreted cautiously.',
            ['task_count' => $tasks->count(), 'completed_count' => $completed],
            $tasks->take(100)->map(fn (Task $task) => 'task:'.$task->id)->all()
        );
    }

    private function currencyCheck(User $user, int $householdId, Collection $expenses): DataQualityCheck
    {
        $currencies = $expenses->pluck('currency')->filter()->unique()->sort()->values()->all();

        return $this->persist(
            $user,
            $householdId,
            'currency_separation',
            count($currencies) > 1 ? 'info' : 'ok',
            'Currency separation',
            count($currencies) > 1
                ? 'Multiple currencies are present. P11 keeps totals and forecasts separate and does not invent exchange rates.'
                : 'Analytics can be interpreted within a single recorded currency for the current history window.',
            ['currencies' => $currencies],
            []
        );
    }

    private function spendingDriftCheck(User $user, int $householdId, Collection $expenses): DataQualityCheck
    {
        $start = now()->startOfMonth()->subMonths(5);
        $months = collect(range(0, 5))->map(fn (int $i) => $start->copy()->addMonths($i)->format('Y-m'));
        $drifts = [];
        $warning = false;

        foreach ($expenses->pluck('currency')->filter()->unique()->sort() as $currency) {
            $series = $months->map(function (string $month) use ($expenses, $currency) {
                return (float) $expenses
                    ->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->format('Y-m') === $month)
                    ->sum(fn (Expense $expense) => (float) $expense->amount);
            })->values();

            $previous = $series->slice(0, 3)->avg();
            $recent = $series->slice(3, 3)->avg();
            $change = $previous > 0 ? (($recent - $previous) / $previous) : null;

            if ($change !== null && abs($change) >= 0.35) {
                $warning = true;
            }

            $drifts[] = [
                'currency' => $currency,
                'previous_3_month_average' => round((float) $previous, 2),
                'recent_3_month_average' => round((float) $recent, 2),
                'relative_change' => $change === null ? null : round($change, 4),
            ];
        }

        return $this->persist(
            $user,
            $householdId,
            'spending_distribution_drift',
            $warning ? 'warning' : 'ok',
            'Spending drift check',
            $warning
                ? 'At least one currency changed by 35% or more between the previous and recent three-month averages. Forecasts should be interpreted with extra caution.'
                : 'No large six-month spending-distribution shift was detected using the simple three-month comparison rule.',
            ['threshold' => 0.35, 'currencies' => $drifts],
            $expenses->take(100)->map(fn (Expense $expense) => 'expense:'.$expense->id)->all()
        );
    }

    private function subscriptionScheduleCheck(User $user, int $householdId, Collection $subscriptions): DataQualityCheck
    {
        $pastDue = $subscriptions->filter(fn (Subscription $subscription) => $subscription->next_billing_date?->isPast());

        return $this->persist(
            $user,
            $householdId,
            'subscription_schedule_freshness',
            $pastDue->isEmpty() ? 'ok' : 'warning',
            'Subscription schedule freshness',
            $pastDue->isEmpty()
                ? 'Active subscription billing dates are current.'
                : $pastDue->count().' active subscription(s) have a next billing date in the past and may need review.',
            ['active_count' => $subscriptions->count(), 'past_due_count' => $pastDue->count()],
            $pastDue->map(fn (Subscription $subscription) => 'subscription:'.$subscription->id)->all()
        );
    }

    private function persist(
        User $user,
        int $householdId,
        string $key,
        string $status,
        string $title,
        string $message,
        array $metrics,
        array $evidenceRefs,
    ): DataQualityCheck {
        return DataQualityCheck::updateOrCreate(
            [
                'household_id' => $householdId,
                'user_id' => $user->id,
                'check_key' => $key,
            ],
            [
                'status' => $status,
                'title' => $title,
                'message' => $message,
                'metrics' => $metrics,
                'evidence_refs' => array_values(array_unique($evidenceRefs)),
                'generated_at' => now(),
            ]
        );
    }
}
