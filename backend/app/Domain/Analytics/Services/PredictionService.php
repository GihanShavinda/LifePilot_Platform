<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\PredictionRecord;
use App\Domain\Finance\Models\{Expense, Subscription};
use App\Domain\Obligations\Models\Task;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PredictionService
{
    public const MODEL_VERSION = 'p11-analytics-v1.0.0';
    public const FEATURE_VERSION = 'p11-features-v1';

    public function __construct(
        private readonly AnalyticsAccess $access,
        private readonly ForecastMath $math,
    ) {
    }

    /** @return array<int,PredictionRecord> */
    public function refresh(User $user): array
    {
        $householdId = $this->access->householdId($user);

        return [
            $this->recurringExpenseForecast($user, $householdId),
            $this->nextSubscriptionCharge($user, $householdId),
            $this->obligationOverdueRisk($user, $householdId),
            $this->expectedMonthlyRecurringSpend($user, $householdId),
            $this->reminderTimingRecommendation($user, $householdId),
        ];
    }

    private function recurringExpenseForecast(User $user, int $householdId): PredictionRecord
    {
        $start = now()->startOfMonth()->subMonths(5);
        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->get()
            ->filter(fn (Expense $expense) => $expense->recurrence_cycle !== null || $expense->source === 'subscription_confirmed');

        $monthKeys = collect(range(0, 5))->map(fn (int $i) => $start->copy()->addMonths($i)->format('Y-m'));
        $predicted = [];
        $evidence = [];
        $sufficientCurrencies = 0;

        foreach ($expenses->pluck('currency')->filter()->unique()->sort() as $currency) {
            $values = $monthKeys->map(function (string $month) use ($expenses, $currency) {
                return round((float) $expenses
                    ->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->format('Y-m') === $month)
                    ->sum(fn (Expense $expense) => (float) $expense->amount), 2);
            })->all();

            $nonZeroMonths = count(array_filter($values, fn ($value) => $value > 0));
            if ($nonZeroMonths < 3) {
                continue;
            }

            $forecast = $this->math->movingAverage($values, 3);
            if ($forecast === null) {
                continue;
            }

            $predicted[] = [
                'currency' => $currency,
                'forecast_next_month' => $forecast,
                'history' => array_map(
                    fn (string $month, float|int $value) => ['month' => $month, 'total' => (float) $value],
                    $monthKeys->all(),
                    $values
                ),
            ];
            $sufficientCurrencies++;
        }

        $evidence = $expenses->take(100)->map(fn (Expense $expense) => 'expense:'.$expense->id)->values()->all();

        if ($sufficientCurrencies === 0) {
            return $this->persist(
                $user,
                $householdId,
                'recurring_expense_forecast',
                'moving_average_3_month',
                ['status' => 'insufficient_history', 'forecast' => []],
                0.0,
                'At least three months with recurring expense observations in the same currency are required before a forecast is produced.',
                $evidence,
                ['expense_ids' => $expenses->pluck('id')->all()]
            );
        }

        return $this->persist(
            $user,
            $householdId,
            'recurring_expense_forecast',
            'moving_average_3_month',
            ['status' => 'ok', 'forecast' => $predicted],
            min(0.9, 0.55 + ($sufficientCurrencies * 0.05)),
            'The forecast is the arithmetic mean of the latest three monthly recurring-cost totals, calculated separately for each currency. No currency conversion is performed.',
            $evidence,
            ['series' => $predicted]
        );
    }

    private function nextSubscriptionCharge(User $user, int $householdId): PredictionRecord
    {
        $subscription = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('next_billing_date')
            ->first();

        if (!$subscription) {
            return $this->persist(
                $user,
                $householdId,
                'likely_next_subscription_charge',
                'deterministic_schedule',
                ['status' => 'insufficient_history', 'charge' => null],
                0.0,
                'No active subscription with a scheduled billing date is available.',
                [],
                ['active_subscription_ids' => []]
            );
        }

        return $this->persist(
            $user,
            $householdId,
            'likely_next_subscription_charge',
            'deterministic_schedule',
            [
                'status' => 'ok',
                'charge' => [
                    'subscription_id' => $subscription->id,
                    'name' => $subscription->name,
                    'provider' => $subscription->provider,
                    'amount' => (float) $subscription->price,
                    'currency' => $subscription->currency,
                    'expected_date' => $subscription->next_billing_date?->toDateString(),
                ],
            ],
            1.0,
            'The next charge is taken directly from the earliest active subscription next_billing_date. This is deterministic scheduling, not an inferred payment transaction.',
            ['subscription:'.$subscription->id],
            [
                'subscription_id' => $subscription->id,
                'next_billing_date' => $subscription->next_billing_date?->toDateString(),
                'price' => (float) $subscription->price,
                'currency' => $subscription->currency,
            ]
        );
    }

    private function obligationOverdueRisk(User $user, int $householdId): PredictionRecord
    {
        $tasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->copy()->addDays(14))
            ->with(['checklist', 'dependencies'])
            ->orderBy('due_at')
            ->limit(50)
            ->get();

        if ($tasks->isEmpty()) {
            return $this->persist(
                $user,
                $householdId,
                'obligation_overdue_risk',
                'interpretable_logistic_score',
                ['status' => 'insufficient_history', 'tasks' => []],
                0.0,
                'No open tasks with due dates in the next fourteen days are available for risk scoring.',
                [],
                ['task_ids' => []]
            );
        }

        $predictions = $tasks->map(function (Task $task) {
            $days = now()->startOfDay()->diffInDays($task->due_at->copy()->startOfDay(), false);
            $priorityWeight = match ($task->priority) {
                'high', 'urgent' => 1.0,
                'medium' => 0.4,
                default => 0.0,
            };
            $deadlineWeight = $days < 0 ? 2.0 : ($days <= 2 ? 1.2 : ($days <= 7 ? 0.6 : 0.1));
            $checklistCount = $task->checklist->count();
            $incompleteChecklist = $checklistCount > 0
                ? $task->checklist->where('is_completed', false)->count() / $checklistCount
                : 0.0;
            $unmetDependencies = $task->dependencies->filter(fn (Task $dependency) => $dependency->status !== 'completed')->count();
            $statusWeight = $task->status === 'in_progress' ? -0.2 : 0.0;

            $score = -1.8
                + $priorityWeight
                + $deadlineWeight
                + (0.8 * $incompleteChecklist)
                + (min(2, $unmetDependencies) * 0.7)
                + $statusWeight;

            $probability = $this->math->sigmoid($score);

            return [
                'task_id' => $task->id,
                'title' => $task->title,
                'due_at' => $task->due_at?->toIso8601String(),
                'priority' => $task->priority,
                'days_to_due' => $days,
                'incomplete_checklist_ratio' => round($incompleteChecklist, 3),
                'unmet_dependencies' => $unmetDependencies,
                'risk_probability' => $probability,
                'risk_level' => $probability >= 0.75 ? 'high' : ($probability >= 0.5 ? 'medium' : 'low'),
            ];
        })->sortByDesc('risk_probability')->values();

        return $this->persist(
            $user,
            $householdId,
            'obligation_overdue_risk',
            'interpretable_logistic_score',
            ['status' => 'ok', 'tasks' => $predictions->all()],
            0.78,
            'Risk is a versioned logistic-style score using due-date proximity, priority, incomplete checklist ratio, unmet dependencies and task status. It is an interpretable heuristic model, not a claim that the user will miss the deadline.',
            $tasks->map(fn (Task $task) => 'task:'.$task->id)->all(),
            ['tasks' => $predictions->all()]
        );
    }

    private function expectedMonthlyRecurringSpend(User $user, int $householdId): PredictionRecord
    {
        $start = now()->startOfMonth()->subMonths(5);
        $recurringExpenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->get()
            ->filter(fn (Expense $expense) => $expense->recurrence_cycle !== null && $expense->source !== 'subscription_confirmed');

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $currencies = $recurringExpenses->pluck('currency')
            ->merge($subscriptions->pluck('currency'))
            ->filter()->unique()->sort()->values();

        if ($currencies->isEmpty()) {
            return $this->persist(
                $user,
                $householdId,
                'expected_monthly_recurring_spend',
                'exponential_smoothing_plus_deterministic_subscriptions',
                ['status' => 'insufficient_history', 'totals' => []],
                0.0,
                'No recurring expenses or active subscriptions are available.',
                [],
                ['expenses' => [], 'subscriptions' => []]
            );
        }

        $months = collect(range(0, 5))->map(fn (int $i) => $start->copy()->addMonths($i)->format('Y-m'));
        $totals = [];

        foreach ($currencies as $currency) {
            $history = $months->map(function (string $month) use ($recurringExpenses, $currency) {
                return round((float) $recurringExpenses
                    ->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->format('Y-m') === $month)
                    ->sum(fn (Expense $expense) => (float) $expense->amount), 2);
            })->all();

            $smoothed = $this->math->exponentialSmoothing($history, 0.35) ?? 0.0;
            $subscriptionMonthly = round((float) $subscriptions
                ->where('currency', $currency)
                ->sum(fn (Subscription $subscription) => $this->math->monthlyEquivalent((float) $subscription->price, $subscription->billing_cycle)), 2);

            $totals[] = [
                'currency' => $currency,
                'smoothed_recurring_expenses' => $smoothed,
                'active_subscription_monthly_equivalent' => $subscriptionMonthly,
                'expected_monthly_total' => round($smoothed + $subscriptionMonthly, 2),
            ];
        }

        $evidence = $recurringExpenses->map(fn (Expense $expense) => 'expense:'.$expense->id)
            ->merge($subscriptions->map(fn (Subscription $subscription) => 'subscription:'.$subscription->id))
            ->take(100)->values()->all();

        return $this->persist(
            $user,
            $householdId,
            'expected_monthly_recurring_spend',
            'exponential_smoothing_plus_deterministic_subscriptions',
            ['status' => 'ok', 'totals' => $totals],
            0.74,
            'Historical recurring expenses use simple exponential smoothing with alpha 0.35. Active subscriptions are converted to a monthly equivalent using their stored billing cycle. Currencies remain separate.',
            $evidence,
            ['totals' => $totals]
        );
    }

    private function reminderTimingRecommendation(User $user, int $householdId): PredictionRecord
    {
        $tasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->copy()->addDays(30)])
            ->with('dependencies')
            ->orderBy('due_at')
            ->limit(30)
            ->get();

        $recommendations = $tasks->map(function (Task $task) {
            $days = max(0, now()->startOfDay()->diffInDays($task->due_at->copy()->startOfDay(), false));
            $hasUnmetDependencies = $task->dependencies->contains(fn (Task $dependency) => $dependency->status !== 'completed');

            $offsetHours = match (true) {
                $days <= 1 => [6, 2],
                $task->priority === 'high' || $task->priority === 'urgent' || $hasUnmetDependencies => [168, 48, 8],
                $task->priority === 'medium' => [72, 24],
                default => [24],
            };

            $times = collect($offsetHours)
                ->map(fn (int $hours) => $task->due_at->copy()->subHours($hours))
                ->filter(fn (Carbon $time) => $time->isFuture())
                ->map(fn (Carbon $time) => $time->toIso8601String())
                ->values()->all();

            return [
                'task_id' => $task->id,
                'title' => $task->title,
                'due_at' => $task->due_at?->toIso8601String(),
                'recommended_reminder_times' => $times,
                'reason' => $hasUnmetDependencies
                    ? 'Earlier reminders are recommended because prerequisites are incomplete.'
                    : 'Reminder offsets are selected deterministically from priority and time remaining.',
            ];
        })->all();

        return $this->persist(
            $user,
            $householdId,
            'reminder_timing_recommendation',
            'deterministic_priority_deadline_rules',
            ['status' => $recommendations === [] ? 'insufficient_history' : 'ok', 'tasks' => $recommendations],
            $recommendations === [] ? 0.0 : 1.0,
            $recommendations === []
                ? 'No open tasks with due dates in the next thirty days require reminder recommendations.'
                : 'Reminder timing is deterministic: it uses stored due dates, task priority and unmet prerequisites. The recommendation does not create reminders automatically.',
            $tasks->map(fn (Task $task) => 'task:'.$task->id)->all(),
            ['tasks' => $recommendations]
        );
    }

    private function persist(
        User $user,
        int $householdId,
        string $type,
        string $method,
        array $prediction,
        float $confidence,
        string $explanation,
        array $evidenceRefs,
        array $fingerprintInput,
    ): PredictionRecord {
        $fingerprint = hash('sha256', json_encode($this->canonicalize($fingerprintInput), JSON_UNESCAPED_SLASHES));

        return PredictionRecord::updateOrCreate(
            [
                'household_id' => $householdId,
                'user_id' => $user->id,
                'prediction_type' => $type,
                'model_version' => self::MODEL_VERSION,
                'feature_version' => self::FEATURE_VERSION,
                'input_fingerprint' => $fingerprint,
            ],
            [
                'method' => $method,
                'prediction' => $prediction,
                'confidence' => max(0, min(1, $confidence)),
                'explanation' => $explanation,
                'evidence_refs' => array_values(array_unique($evidenceRefs)),
                'generated_at' => now(),
            ]
        );
    }

    private function canonicalize(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        if (array_is_list($value)) {
            usort($value, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        } else {
            ksort($value);
        }

        return $value;
    }
}
