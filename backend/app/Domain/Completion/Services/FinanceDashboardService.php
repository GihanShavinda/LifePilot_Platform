<?php

namespace App\Domain\Completion\Services;

use App\Domain\Finance\Models\{Expense, Subscription};
use App\Domain\Users\Models\User;
use Carbon\Carbon;

class FinanceDashboardService
{
    public function __construct(private readonly CompletionAccess $access) {}

    public function build(User $user, int $months = 6): array
    {
        $householdId = $this->access->householdId($user);
        $months = max(3, min(24, $months));
        $start = now()->startOfMonth()->subMonths($months - 1);

        $expenses = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereDate('expense_date', '>=', $start->toDateString())
            ->with('category:id,name')
            ->get();

        $monthKeys = collect(range(0, $months - 1))
            ->map(fn (int $offset) => $start->copy()->addMonths($offset)->format('Y-m'));

        $monthly = [];
        foreach ($expenses->pluck('currency')->filter()->unique()->sort() as $currency) {
            foreach ($monthKeys as $month) {
                $items = $expenses->filter(fn (Expense $expense) => $expense->currency === $currency && $expense->expense_date?->format('Y-m') === $month);
                $monthly[] = [
                    'month' => $month,
                    'currency' => $currency,
                    'total' => round((float) $items->sum(fn (Expense $expense) => (float) $expense->amount), 2),
                    'count' => $items->count(),
                ];
            }
        }

        $recurring = $expenses
            ->filter(fn (Expense $expense) => $expense->recurrence_cycle !== null || $expense->source === 'subscription_confirmed')
            ->groupBy('currency')
            ->map(fn ($items, $currency) => [
                'currency' => $currency,
                'recorded_total' => round((float) $items->sum(fn (Expense $expense) => (float) $expense->amount), 2),
                'records' => $items->count(),
            ])->values()->all();

        $categories = $expenses
            ->groupBy(fn (Expense $expense) => ($expense->currency ?: 'UNKNOWN').'|'.($expense->category?->name ?? 'Uncategorized'))
            ->map(function ($items, $key) {
                [$currency, $category] = explode('|', $key, 2);
                return [
                    'currency' => $currency,
                    'category' => $category,
                    'total' => round((float) $items->sum(fn (Expense $expense) => (float) $expense->amount), 2),
                    'count' => $items->count(),
                ];
            })->sortByDesc('total')->values()->all();

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->get();

        $subscriptionTrend = collect(range(0, $months - 1))->map(function (int $offset) use ($start, $subscriptions) {
            $monthStart = $start->copy()->addMonths($offset)->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $active = $subscriptions->filter(function (Subscription $subscription) use ($monthEnd) {
                return $subscription->created_at && $subscription->created_at->lte($monthEnd)
                    && !($subscription->deleted_at && $subscription->deleted_at->lte($monthEnd));
            });

            return [
                'month' => $monthStart->format('Y-m'),
                'active_subscriptions' => $active->where('status', 'active')->count(),
                'currencies' => $active->where('status', 'active')->groupBy('currency')->map(fn ($items, $currency) => [
                    'currency' => $currency,
                    'total_recorded_price' => round((float) $items->sum(fn (Subscription $subscription) => (float) $subscription->price), 2),
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'period' => ['months' => $months, 'from' => $start->toDateString(), 'to' => now()->endOfMonth()->toDateString()],
            'monthly_expenses' => $monthly,
            'recurring_commitments' => $recurring,
            'category_breakdown' => $categories,
            'subscription_trend' => $subscriptionTrend,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
