<?php

namespace App\Domain\Analytics\Services;

use App\Domain\Analytics\Models\{AnalyticsInsight, PredictionRecord};
use App\Domain\Finance\Models\{Asset, Expense, Subscription, Warranty};
use App\Domain\Users\Models\User;
use Illuminate\Support\Collection;

class InsightService
{
    public function __construct(
        private readonly AnalyticsAccess $access,
        private readonly ForecastMath $math,
    ) {
    }

    /** @return array<int,AnalyticsInsight> */
    public function refresh(User $user): array
    {
        $householdId = $this->access->householdId($user);
        $created = [];

        $this->upsertRecurringCommitmentInsight($user, $householdId, $created);
        $this->upsertTaskRiskInsight($user, $householdId, $created);
        $this->upsertSpendingChangeInsight($user, $householdId, $created);
        $this->upsertWarrantyInsight($user, $householdId, $created);

        $validKeys = collect($created)->pluck('insight_key')->all();
        AnalyticsInsight::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->when($validKeys !== [], fn ($q) => $q->whereNotIn('insight_key', $validKeys))
            ->when($validKeys === [], fn ($q) => $q)
            ->delete();

        return $created;
    }

    private function upsertRecurringCommitmentInsight(User $user, int $householdId, array &$created): void
    {
        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($subscriptions->groupBy('currency') as $currency => $items) {
            $new = $items->filter(fn (Subscription $subscription) => $subscription->created_at?->gte(now()->startOfMonth()));
            if ($new->isEmpty()) {
                continue;
            }

            $current = (float) $items->sum(fn (Subscription $subscription) => $this->math->monthlyEquivalent((float) $subscription->price, $subscription->billing_cycle));
            $newAmount = (float) $new->sum(fn (Subscription $subscription) => $this->math->monthlyEquivalent((float) $subscription->price, $subscription->billing_cycle));
            $baseline = $current - $newAmount;

            if ($baseline <= 0 || $newAmount <= 0) {
                continue;
            }

            $percent = round(($newAmount / $baseline) * 100, 1);
            if ($percent < 5) {
                continue;
            }

            $evidence = $items->map(fn (Subscription $subscription) => 'subscription:'.$subscription->id)->all();
            $created[] = $this->persist(
                $user,
                $householdId,
                'recurring_commitment_change_'.$currency,
                'recurring_commitment_change',
                $percent >= 20 ? 'warning' : 'info',
                'Recurring commitments changed',
                "Your active monthly subscription commitment in {$currency} is approximately {$percent}% higher because {$new->count()} subscription(s) were added this month.",
                [
                    'currency' => $currency,
                    'percent_change' => $percent,
                    'new_subscriptions' => $new->count(),
                    'baseline_monthly_equivalent' => round($baseline, 2),
                    'current_monthly_equivalent' => round($current, 2),
                ],
                $evidence
            );
        }
    }

    private function upsertTaskRiskInsight(User $user, int $householdId, array &$created): void
    {
        $record = PredictionRecord::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('prediction_type', 'obligation_overdue_risk')
            ->latest('generated_at')
            ->first();

        if (!$record || ($record->prediction['status'] ?? null) !== 'ok') {
            return;
        }

        $elevated = collect($record->prediction['tasks'] ?? [])->filter(function (array $task) {
            return ($task['risk_probability'] ?? 0) >= 0.65 && ($task['days_to_due'] ?? 99) <= 2;
        })->values();

        if ($elevated->isEmpty()) {
            return;
        }

        $created[] = $this->persist(
            $user,
            $householdId,
            'elevated_overdue_risk',
            'overdue_risk',
            'warning',
            'Tasks at elevated overdue risk',
            $elevated->count().' important task(s) have elevated overdue risk because they are due within 48 hours and have risk factors such as high priority or incomplete prerequisites.',
            ['task_count' => $elevated->count(), 'threshold' => 0.65],
            $elevated->map(fn (array $task) => 'task:'.$task['task_id'])->all()
        );
    }

    private function upsertSpendingChangeInsight(User $user, int $householdId, array &$created): void
    {
        $startCurrent = now()->startOfMonth();
        $startPrevious = $startCurrent->copy()->subMonth();
        $endPrevious = $startCurrent->copy()->subSecond();

        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $startPrevious->toDateString())
            ->get();

        foreach ($expenses->pluck('currency')->filter()->unique()->sort() as $currency) {
            $previous = $expenses->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->between($startPrevious, $endPrevious));
            $current = $expenses->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->gte($startCurrent));
            $previousTotal = (float) $previous->sum(fn (Expense $expense) => (float) $expense->amount);
            $currentTotal = (float) $current->sum(fn (Expense $expense) => (float) $expense->amount);

            if ($previousTotal <= 0) {
                continue;
            }

            $percent = round((($currentTotal - $previousTotal) / $previousTotal) * 100, 1);
            if (abs($percent) < 10) {
                continue;
            }

            $direction = $percent > 0 ? 'higher' : 'lower';
            $evidence = $previous->merge($current)->map(fn (Expense $expense) => 'expense:'.$expense->id)->all();

            $created[] = $this->persist(
                $user,
                $householdId,
                'month_spending_change_'.$currency,
                'month_over_month_spending',
                abs($percent) >= 25 ? 'warning' : 'info',
                'Month-to-month spending changed',
                "Recorded spending in {$currency} is ".abs($percent)."% {$direction} this month than last month based only on accessible expense records.",
                [
                    'currency' => $currency,
                    'percent_change' => $percent,
                    'previous_total' => round($previousTotal, 2),
                    'current_total' => round($currentTotal, 2),
                ],
                $evidence
            );
        }
    }

    private function upsertWarrantyInsight(User $user, int $householdId, array &$created): void
    {
        $assetIds = Asset::accessibleTo($user)->where('household_id', $householdId)->pluck('id');
        $warranties = Warranty::query()
            ->where('household_id', $householdId)
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('end_date', [today(), today()->addDays(30)])
            ->with('asset:id,name')
            ->orderBy('end_date')
            ->get();

        if ($warranties->isEmpty()) {
            return;
        }

        $created[] = $this->persist(
            $user,
            $householdId,
            'warranties_expiring_30_days',
            'warranty_expiry',
            'warning',
            'Warranty coverage expiring soon',
            $warranties->count().' accessible warranty record(s) expire within the next 30 days.',
            [
                'count' => $warranties->count(),
                'earliest_end_date' => $warranties->first()?->end_date?->toDateString(),
            ],
            $warranties->map(fn (Warranty $warranty) => 'warranty:'.$warranty->id)->all()
        );
    }

    private function persist(
        User $user,
        int $householdId,
        string $key,
        string $type,
        string $severity,
        string $title,
        string $message,
        array $metrics,
        array $evidenceRefs,
    ): AnalyticsInsight {
        if ($evidenceRefs === []) {
            throw new \LogicException('P11 insights require at least one evidence reference.');
        }

        return AnalyticsInsight::updateOrCreate(
            [
                'household_id' => $householdId,
                'user_id' => $user->id,
                'insight_key' => $key,
            ],
            [
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'metrics' => $metrics,
                'evidence_refs' => array_values(array_unique($evidenceRefs)),
                'model_version' => PredictionService::MODEL_VERSION,
                'feature_version' => PredictionService::FEATURE_VERSION,
                'generated_at' => now(),
            ]
        );
    }
}
