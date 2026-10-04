<?php

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Enums\AssistantIntent;
use App\Domain\Collaboration\Services\SharedResourceService;
use App\Domain\Documents\Models\DocumentExtraction;
use App\Domain\Finance\Models\{Asset, Expense, Subscription, Warranty};
use App\Domain\Graph\Services\HybridSearchService;
use App\Domain\Obligations\Models\{Obligation, Task};
use App\Domain\Scheduling\Models\CalendarEvent;
use App\Domain\Users\Models\User;

class EvidenceBundleBuilder
{
    public function __construct(
        private HybridSearchService $search,
        private SharedResourceService $sharing,
    ) {
    }

    public function build(User $user, int $householdId, string $question, AssistantIntent $intent): array
    {
        $evidence = [];
        $add = function (string $key, string $type, int|string $id, string $title, array $facts, array $source) use (&$evidence) {
            $evidence[$key] = [
                'key' => $key,
                'type' => $type,
                'id' => $id,
                'title' => $title,
                'facts' => $facts,
                'source' => $source,
            ];
        };

        if (in_array($intent, [
            AssistantIntent::UpcomingObligations,
            AssistantIntent::RecommendNextAction,
            AssistantIntent::Search,
            AssistantIntent::DraftTask,
            AssistantIntent::DraftEvent,
        ], true)) {
            $start = now()->startOfDay();
            $end = now()->copy()->addDays(7)->endOfDay();

            // Obligations were not made household-shareable in P10. Keep them private to their owner.
            foreach (Obligation::where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereBetween('due_at', [$start, $end])
                ->orderBy('due_at')->limit(20)->get() as $o) {
                $add("obligation:{$o->id}", 'obligation', $o->id, $o->title, [
                    'type' => $o->type,
                    'amount' => $o->amount,
                    'currency' => $o->currency,
                    'due_at' => $o->due_at?->toIso8601String(),
                    'status' => $o->status,
                ], ['record' => 'obligations', 'id' => $o->id, 'document_id' => $o->document_id]);
            }

            foreach (Task::accessibleTo($user)
                ->where('household_id', $householdId)
                ->whereIn('status', ['pending', 'in_progress'])
                ->whereNotNull('due_at')
                ->orderBy('due_at')->limit(20)->get() as $t) {
                $add("task:{$t->id}", 'task', $t->id, $t->title, [
                    'due_at' => $t->due_at?->toIso8601String(),
                    'priority' => $t->priority,
                    'status' => $t->effectiveStatus(),
                ], ['record' => 'tasks', 'id' => $t->id, 'document_id' => $t->document_id]);
            }
        }

        if (in_array($intent, [
            AssistantIntent::Calculate,
            AssistantIntent::Explain,
            AssistantIntent::Compare,
            AssistantIntent::Search,
            AssistantIntent::RecommendNextAction,
        ], true)) {
            foreach (Expense::accessibleTo($user)
                ->where('household_id', $householdId)
                ->whereDate('expense_date', '>=', now()->copy()->startOfMonth()->subMonth())
                ->latest('expense_date')->limit(40)->get() as $x) {
                $add("expense:{$x->id}", 'expense', $x->id, $x->title, [
                    'amount' => $x->amount,
                    'currency' => $x->currency,
                    'expense_date' => $x->expense_date?->toDateString(),
                    'merchant_id' => $x->merchant_id,
                    'category_id' => $x->expense_category_id,
                ], ['record' => 'expenses', 'id' => $x->id, 'document_id' => $x->document_id]);
            }

            // Subscriptions remain private because P10 did not define them as shared resources.
            foreach (Subscription::where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->where('status', 'active')->limit(30)->get() as $s) {
                $add("subscription:{$s->id}", 'subscription', $s->id, $s->name, [
                    'provider' => $s->provider,
                    'price' => $s->price,
                    'currency' => $s->currency,
                    'billing_cycle' => $s->billing_cycle,
                    'next_billing_date' => $s->next_billing_date?->toDateString(),
                    'cancellation_deadline' => $s->cancellation_deadline?->toDateString(),
                ], ['record' => 'subscriptions', 'id' => $s->id, 'document_id' => $s->document_id]);
            }
        }

        if (in_array($intent, [
            AssistantIntent::Search,
            AssistantIntent::Summarize,
            AssistantIntent::Compare,
            AssistantIntent::Explain,
            AssistantIntent::RecommendNextAction,
            AssistantIntent::DraftTask,
            AssistantIntent::DraftEvent,
        ], true)) {
            $visibleAssetIds = Asset::accessibleTo($user)
                ->where('household_id', $householdId)
                ->limit(30)
                ->pluck('id');

            foreach (Asset::accessibleTo($user)
                ->where('household_id', $householdId)
                ->whereIn('id', $visibleAssetIds)
                ->limit(30)->get() as $a) {
                $add("asset:{$a->id}", 'asset', $a->id, $a->name, [
                    'brand' => $a->brand,
                    'model' => $a->model,
                    'serial_number' => $a->serial_number,
                    'purchase_date' => $a->purchase_date?->toDateString(),
                    'purchase_price' => $a->purchase_price,
                    'currency' => $a->currency,
                    'location' => $a->location,
                    'status' => $a->status,
                ], ['record' => 'assets', 'id' => $a->id, 'document_id' => $a->document_id]);
            }

            foreach (Warranty::where('household_id', $householdId)
                ->whereIn('asset_id', $visibleAssetIds)
                ->limit(30)->get() as $w) {
                $add("warranty:{$w->id}", 'warranty', $w->id, 'Warranty #'.$w->id, [
                    'provider' => $w->provider,
                    'start_date' => $w->start_date?->toDateString(),
                    'end_date' => $w->end_date?->toDateString(),
                    'status' => $w->status,
                    'asset_id' => $w->asset_id,
                ], ['record' => 'warranties', 'id' => $w->id, 'proof_document_id' => $w->proof_document_id]);
            }

            foreach (CalendarEvent::accessibleTo($user)
                ->where('household_id', $householdId)
                ->where('starts_at', '>=', now()->copy()->subDay())
                ->orderBy('starts_at')->limit(20)->get() as $c) {
                $add("event:{$c->id}", 'event', $c->id, $c->title, [
                    'starts_at' => $c->starts_at?->toIso8601String(),
                    'ends_at' => $c->ends_at?->toIso8601String(),
                    'timezone' => $c->timezone,
                    'location' => $c->location,
                    'status' => $c->status,
                ], ['record' => 'calendar_events', 'id' => $c->id, 'document_id' => $c->document_id, 'task_id' => $c->task_id]);
            }
        }

        foreach (DocumentExtraction::whereHas('document', function ($q) use ($user, $householdId) {
                $q->accessibleTo($user)->where('household_id', $householdId);
            })
            ->with([
                'document:id,user_id,household_id,title',
                'fields' => fn ($q) => $q->whereIn('review_status', ['accepted', 'edited']),
            ])
            ->latest('id')->limit(20)->get() as $ex) {
            foreach ($ex->fields as $f) {
                $value = is_array($f->normalized_value)
                    ? json_encode($f->normalized_value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : (string) ($f->normalized_value ?? $f->value ?? '');
                $key = "extraction_field:{$f->id}";
                $add($key, 'accepted_extraction', $f->id, ($ex->document?->title ?? 'Document').' — '.$f->field_name, [
                    'field_name' => $f->field_name,
                    'value' => $value,
                    'confidence' => $f->confidence,
                    'page' => $f->page,
                    'evidence_text' => $f->evidence_text,
                ], ['record' => 'extracted_fields', 'id' => $f->id, 'document_id' => $ex->document_id, 'document_extraction_id' => $ex->id]);
            }
        }

        foreach ($this->search->search($householdId, $question, [], 40) as $r) {
            $sources = array_values(array_filter(
                $r['sources'] ?? [],
                fn (array $source) => $this->sharing->canAccessSource($user, $source)
            ));
            if (!$sources) {
                continue;
            }
            $key = 'search:'.$r['kind'].':'.$r['id'];
            $add($key, $r['kind'], $r['id'], $r['title'], [
                'snippet' => $r['snippet'],
                'score' => $r['score'],
                'metadata' => $r['metadata'],
            ], ['search_sources' => $sources]);
        }

        return array_slice(array_values($evidence), 0, (int) config('lifepilot_ai.max_evidence_items', 60));
    }
}
