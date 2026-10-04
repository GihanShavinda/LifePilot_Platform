<?php

namespace App\Domain\Completion\Services;

use App\Domain\Analytics\Models\AnalyticsInsight;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\{Asset, Expense, Subscription, Warranty};
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;

class MainDashboardService
{
    public function __construct(private readonly CompletionAccess $access) {}

    public function build(User $user): array
    {
        $householdId = $this->access->householdId($user);
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        $thirtyDays = now()->copy()->addDays(30);
        $ninetyDays = now()->copy()->addDays(90);

        $todayTasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereBetween('due_at', [$todayStart, $todayEnd])
            ->orderBy('due_at')
            ->limit(12)
            ->get(['id', 'title', 'priority', 'status', 'due_at'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'priority' => $task->priority,
                'status' => $task->status,
                'due_at' => $task->due_at?->toIso8601String(),
                'source_ref' => 'task:'.$task->id,
            ])->values()->all();

        $upcomingObligations = Obligation::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereBetween('due_at', [now(), $thirtyDays])
            ->orderBy('due_at')
            ->limit(12)
            ->get()
            ->map(fn (Obligation $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type' => $item->type,
                'due_at' => $item->due_at?->toIso8601String(),
                'amount' => $item->amount,
                'currency' => $item->currency,
                'source_ref' => 'obligation:'.$item->id,
            ])->values()->all();

        $overdueTasks = Task::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(12)
            ->get(['id', 'title', 'priority', 'status', 'due_at'])
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'kind' => 'task',
                'title' => $task->title,
                'due_at' => $task->due_at?->toIso8601String(),
                'priority' => $task->priority,
                'source_ref' => 'task:'.$task->id,
            ]);

        $overdueObligations = Obligation::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(12)
            ->get()
            ->map(fn (Obligation $item) => [
                'id' => $item->id,
                'kind' => 'obligation',
                'title' => $item->title,
                'due_at' => $item->due_at?->toIso8601String(),
                'priority' => null,
                'source_ref' => 'obligation:'.$item->id,
            ]);

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->orderBy('next_billing_date')
            ->get();

        $upcomingPayments = $subscriptions
            ->filter(fn (Subscription $subscription) => $subscription->next_billing_date && $subscription->next_billing_date->lte($thirtyDays))
            ->map(fn (Subscription $subscription) => [
                'kind' => 'subscription',
                'id' => $subscription->id,
                'title' => $subscription->name,
                'amount' => (float) $subscription->price,
                'currency' => $subscription->currency,
                'due_date' => $subscription->next_billing_date?->toDateString(),
                'source_ref' => 'subscription:'.$subscription->id,
            ]);

        $expensePayments = Expense::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereNotNull('next_occurrence_date')
            ->whereBetween('next_occurrence_date', [today(), $thirtyDays->toDateString()])
            ->get()
            ->map(fn (Expense $expense) => [
                'kind' => 'recurring_expense',
                'id' => $expense->id,
                'title' => $expense->title,
                'amount' => (float) $expense->amount,
                'currency' => $expense->currency,
                'due_date' => $expense->next_occurrence_date?->toDateString(),
                'source_ref' => 'expense:'.$expense->id,
            ]);

        $assetIds = Asset::accessibleTo($user)
            ->where('household_id', $householdId)
            ->pluck('id');

        $expiringWarranties = Warranty::query()
            ->where('household_id', $householdId)
            ->whereIn('asset_id', $assetIds)
            ->whereBetween('end_date', [today(), $ninetyDays->toDateString()])
            ->with('asset:id,name')
            ->orderBy('end_date')
            ->limit(10)
            ->get()
            ->map(fn (Warranty $warranty) => [
                'id' => $warranty->id,
                'asset_id' => $warranty->asset_id,
                'asset_name' => $warranty->asset?->name,
                'provider' => $warranty->provider,
                'end_date' => $warranty->end_date?->toDateString(),
                'source_ref' => 'warranty:'.$warranty->id,
            ])->values()->all();

        $recentDocuments = Document::accessibleTo($user)
            ->where('household_id', $householdId)
            ->with('category:id,name')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Document $document) => [
                'id' => $document->id,
                'title' => $document->title,
                'filename' => $document->original_filename,
                'category' => $document->category?->name ?? 'Uncategorized',
                'processing_status' => is_object($document->processing_status) ? $document->processing_status->value : $document->processing_status,
                'created_at' => $document->created_at?->toIso8601String(),
                'source_ref' => 'document:'.$document->id,
            ])->values()->all();

        $calendar = CalendarEvent::accessibleTo($user)
            ->where('household_id', $householdId)
            ->whereBetween('starts_at', [now(), now()->copy()->addDays(14)])
            ->orderBy('starts_at')
            ->limit(10)
            ->get(['id', 'title', 'starts_at', 'ends_at', 'timezone', 'status'])
            ->map(fn (CalendarEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'starts_at' => $event->starts_at?->toIso8601String(),
                'ends_at' => $event->ends_at?->toIso8601String(),
                'timezone' => $event->timezone,
                'status' => $event->status,
                'source_ref' => 'calendar_event:'.$event->id,
            ])->values()->all();

        $recommendations = AnalyticsInsight::query()
            ->where('household_id', $householdId)
            ->where('user_id', $user->id)
            ->latest('generated_at')
            ->limit(8)
            ->get()
            ->map(fn (AnalyticsInsight $insight) => [
                'id' => $insight->id,
                'severity' => $insight->severity,
                'title' => $insight->title,
                'message' => $insight->message,
                'evidence_refs' => $insight->evidence_refs ?? [],
                'model_version' => $insight->model_version,
                'feature_version' => $insight->feature_version,
                'generated_at' => $insight->generated_at?->toIso8601String(),
            ])->values()->all();

        return [
            'today_tasks' => $todayTasks,
            'upcoming_obligations' => $upcomingObligations,
            'overdue_items' => $overdueTasks->concat($overdueObligations)->sortBy('due_at')->values()->all(),
            'upcoming_payments' => $upcomingPayments->concat($expensePayments)->sortBy('due_date')->values()->all(),
            'active_subscriptions' => [
                'count' => $subscriptions->count(),
                'items' => $subscriptions->take(8)->map(fn (Subscription $subscription) => [
                    'id' => $subscription->id,
                    'name' => $subscription->name,
                    'price' => (float) $subscription->price,
                    'currency' => $subscription->currency,
                    'billing_cycle' => $subscription->billing_cycle,
                    'next_billing_date' => $subscription->next_billing_date?->toDateString(),
                    'source_ref' => 'subscription:'.$subscription->id,
                ])->values()->all(),
            ],
            'expiring_warranties' => $expiringWarranties,
            'recent_documents' => $recentDocuments,
            'calendar_preview' => $calendar,
            'ai_recommendations' => $recommendations,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
