<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\{Asset, Expense, MaintenanceRecord, Subscription, Warranty};
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AnalyticsService
{
    public function __construct(
        private readonly AnalyticsAccess $access,
        private readonly ForecastMath $math,
    ) {
    }

    public function dashboard(User $user, int $months = 6): array
    {
        $months = max(3, min(24, $months));
        $householdId = $this->access->householdId($user);
        $start = now()->startOfMonth()->subMonths($months - 1);

        return [
            'period' => [
                'months' => $months,
                'from' => $start->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ],
            'spending_trend' => $this->spendingTrend($user, $householdId, $start, $months),
            'recurring_cost_trend' => $this->recurringCostTrend($user, $householdId, $start, $months),
            'task_completion' => $this->taskCompletion($user, $householdId, $start),
            'overdue_task_trend' => $this->overdueTrend($user, $householdId, $start, $months),
            'document_categories' => $this->documentCategories($user, $householdId),
            'subscription_cost' => $this->subscriptionCost($user, $householdId),
            'upcoming_obligations' => $this->upcomingObligations($user, $householdId),
            'warranty_expirations' => $this->warrantyExpirations($user, $householdId),
            'maintenance_schedule' => $this->maintenanceSchedule($user, $householdId),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function monthKeys(Carbon $start, int $months): array
    {
        return collect(range(0, $months - 1))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset)->format('Y-m'))
            ->all();
    }

    private function spendingTrend(User $user, int $householdId, Carbon $start, int $months): array
    {
        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->orderBy('expense_date')
            ->get();

        return $this->currencyMonthSeries($expenses, $start, $months);
    }

    private function recurringCostTrend(User $user, int $householdId, Carbon $start, int $months): array
    {
        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->get()
            ->filter(fn (Expense $expense) => $expense->recurrence_cycle !== null || $expense->source === 'subscription_confirmed');

        return $this->currencyMonthSeries($expenses, $start, $months);
    }

    private function currencyMonthSeries(Collection $expenses, Carbon $start, int $months): array
    {
        $currencies = $expenses->pluck('currency')->filter()->unique()->sort()->values();
        $series = [];

        foreach ($currencies as $currency) {
            foreach ($this->monthKeys($start, $months) as $month) {
                $matching = $expenses->filter(
                    fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->format('Y-m') === $month
                );

                $series[] = [
                    'month' => $month,
                    'currency' => $currency,
                    'total' => round((float) $matching->sum(fn (Expense $expense) => (float) $expense->amount), 2),
                    'count' => $matching->count(),
                ];
            }
        }

        return $series;
    }

    private function taskCompletion(User $user, int $householdId, Carbon $start): array
    {
        $tasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->where('created_at', '>=', $start)
            ->get();

        $completed = $tasks->where('status', 'completed')->count();
        $total = $tasks->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'rate' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
        ];
    }

    private function overdueTrend(User $user, int $householdId, Carbon $start, int $months): array
    {
        $tasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $start)
            ->get();

        return collect($this->monthKeys($start, $months))->map(function (string $month) use ($tasks) {
            $matching = $tasks->filter(fn (Task $task) => $task->due_at?->format('Y-m') === $month);
            $overdue = $matching->filter(function (Task $task) {
                if ($task->completed_at && $task->due_at) {
                    return $task->completed_at->greaterThan($task->due_at);
                }

                return in_array($task->status, ['pending', 'in_progress'], true)
                    && $task->due_at
                    && $task->due_at->isPast();
            })->count();

            return [
                'month' => $month,
                'due_tasks' => $matching->count(),
                'overdue_tasks' => $overdue,
            ];
        })->values()->all();
    }

    private function documentCategories(User $user, int $householdId): array
    {
        return Document::accessibleTo($user)
            ->where('household_id', $householdId)
            ->with('category:id,name')
            ->get()
            ->groupBy(fn (Document $document) => $document->category?->name ?? 'Uncategorized')
            ->map(fn (Collection $items, string $name) => ['category' => $name, 'count' => $items->count()])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function subscriptionCost(User $user, int $householdId): array
    {
        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        return $subscriptions
            ->groupBy('currency')
            ->map(function (Collection $items, string $currency) {
                return [
                    'currency' => $currency,
                    'active_subscriptions' => $items->count(),
                    'monthly_equivalent' => round((float) $items->sum(
                        fn (Subscription $subscription) => $this->math->monthlyEquivalent((float) $subscription->price, $subscription->billing_cycle)
                    ), 2),
                ];
            })
            ->values()
            ->all();
    }

    private function upcomingObligations(User $user, int $householdId): array
    {
        return Obligation::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->copy()->addDays(30)])
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(fn (Obligation $obligation) => [
                'id' => $obligation->id,
                'title' => $obligation->title,
                'type' => $obligation->type,
                'due_at' => $obligation->due_at?->toIso8601String(),
                'amount' => $obligation->amount,
                'currency' => $obligation->currency,
                'source_ref' => 'obligation:'.$obligation->id,
            ])->all();
    }

    private function warrantyExpirations(User $user, int $householdId): array
    {
        $assetIds = Asset::accessibleTo($user)
            ->where('household_id', $householdId)
            ->pluck('id');

        return Warranty::query()
            ->where('household_id', $householdId)
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('end_date', [today(), today()->addDays(90)])
            ->with('asset:id,name')
            ->orderBy('end_date')
            ->limit(20)
            ->get()
            ->map(fn (Warranty $warranty) => [
                'id' => $warranty->id,
                'asset_id' => $warranty->asset_id,
                'asset_name' => $warranty->asset?->name,
                'provider' => $warranty->provider,
                'end_date' => $warranty->end_date?->toDateString(),
                'source_ref' => 'warranty:'.$warranty->id,
            ])->all();
    }

    private function maintenanceSchedule(User $user, int $householdId): array
    {
        $assetIds = Asset::accessibleTo($user)
            ->where('household_id', $householdId)
            ->pluck('id');

        return MaintenanceRecord::query()
            ->where('household_id', $householdId)
            ->whereIn('asset_id', $assetIds)
            ->whereNotNull('next_due_date')
            ->whereBetween('next_due_date', [today(), today()->addDays(90)])
            ->with('asset:id,name')
            ->orderBy('next_due_date')
            ->limit(20)
            ->get()
            ->map(fn (MaintenanceRecord $record) => [
                'id' => $record->id,
                'asset_id' => $record->asset_id,
                'asset_name' => $record->asset?->name,
                'title' => $record->title,
                'next_due_date' => $record->next_due_date?->toDateString(),
                'source_ref' => 'maintenance_record:'.$record->id,
            ])->all();
    }
}
