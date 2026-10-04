<?php

namespace App\Domain\Finance\Controllers;

use App\Domain\Finance\Models\{Subscription, SubscriptionPayment, Expense, Merchant};
use App\Domain\Finance\Services\{FinanceAccess, LifeFinanceReminderService, FinanceCalculations};
use App\Support\ApiResponse;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class SubscriptionController
{
    public function __construct(private FinanceAccess $access, private LifeFinanceReminderService $reminders, private FinanceCalculations $calc) {}
    private function rules(bool $create = true): array
    {
        $req = $create ? 'required' : 'sometimes';
        return ['name' => $req . '|string|max:255', 'provider' => $req . '|string|max:255', 'price' => $req . '|numeric|gt:0', 'currency' => $req . '|string|size:3', 'billing_cycle' => $req . '|in:daily,weekly,monthly,quarterly,yearly', 'next_billing_date' => $req . '|date', 'renewal_type' => 'sometimes|in:automatic,manual', 'cancellation_deadline' => 'nullable|date', 'status' => 'sometimes|in:active,paused,cancelled', 'document_id' => 'nullable|integer'];
    }
    private function key(string $provider, string $name): string
    {
        return hash('sha256', mb_strtolower(preg_replace('/\s+/u', ' ', trim($provider))) . '|' . mb_strtolower(preg_replace('/\s+/u', ' ', trim($name))));
    }
    private function prepare(array $d, int $h): array
    {
        $this->access->requireDocument($h, $d['document_id'] ?? null);
        if (isset($d['currency'])) $d['currency'] = strtoupper($d['currency']);
        if (isset($d['name'], $d['provider'])) $d['dedupe_key'] = $this->key($d['provider'], $d['name']);
        if (isset($d['provider'])) {
            $n = trim($d['provider']);
            $m = Merchant::firstOrCreate(['household_id' => $h, 'normalized_name' => mb_strtolower($n)], ['name' => $n]);
            $d['merchant_id'] = $m->id;
        }
        if (!empty($d['cancellation_deadline']) && !empty($d['next_billing_date']) && $d['cancellation_deadline'] > $d['next_billing_date']) throw ValidationException::withMessages(['cancellation_deadline' => 'Cancellation deadline must not follow the next billing date.']);
        return $d;
    }
    public function index(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        return ApiResponse::success(['subscriptions' => Subscription::where('household_id', $h)->with('payments')->orderBy('next_billing_date')->paginate(50)]);
    }
    public function create(Request $r): JsonResponse
    {
        $h = $this->access->household($r, true);
        $d = $this->prepare($r->validate($this->rules()), $h);
        if (Subscription::withTrashed()->where('household_id', $h)->where('dedupe_key', $d['dedupe_key'])->exists()) throw ValidationException::withMessages(['name' => 'A subscription with this provider and name already exists.']);
        return DB::transaction(function () use ($r, $h, $d) {
            $s = Subscription::create(array_merge($d, ['household_id' => $h, 'user_id' => $r->user()->id]));
            $this->reminders->subscription($s);
            return ApiResponse::success(['subscription' => $s->fresh()], 201);
        });
    }
    public function update(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $s = Subscription::where('household_id', $h)->findOrFail($id);
        $d = $r->validate($this->rules(false));
        $d = $this->prepare(array_merge(['name' => $s->name, 'provider' => $s->provider, 'next_billing_date' => $s->next_billing_date->toDateString()], $d), $h);
        if ($d['dedupe_key'] !== $s->dedupe_key && Subscription::withTrashed()->where('household_id', $h)->where('dedupe_key', $d['dedupe_key'])->exists()) throw ValidationException::withMessages(['name' => 'Duplicate subscription.']);
        if (isset($d['price']) && (float)$d['price'] !== (float)$s->price) {
            $d['previous_price'] = $s->price;
            $d['last_price_change_at'] = now();
        }
        if (isset($d['next_billing_date']) && $d['next_billing_date'] !== $s->next_billing_date->toDateString()) $d['last_generated_billing_date'] = null;
        $s->update($d);
        if ($s->status !== 'active') $this->reminders->cancelSubscriptionTask($s);
        else $this->reminders->subscription($s->fresh());
        return ApiResponse::success(['subscription' => $s->fresh()]);
    }
    public function remove(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $s = Subscription::where('household_id', $h)->findOrFail($id);
        $this->reminders->cancelSubscriptionTask($s);
        $s->update(['status' => 'cancelled']);
        return ApiResponse::success(['subscription' => $s->fresh(), 'note' => 'Tracking changed; no external subscription has been cancelled.']);
    }
    // public function recordPayment(Request $r, int $id): JsonResponse
    // {
    //     $h = $this->access->household($r, true);
    //     $s = Subscription::where('household_id', $h)->findOrFail($id);
    //     $d = $r->validate(['amount' => 'required|numeric|gt:0', 'billing_date' => 'required|date', 'document_id' => 'nullable|integer']);
    //     $this->access->requireDocument($h, $d['document_id'] ?? null);
    //     if ($s->payments()->where('billing_date', $d['billing_date'])->exists()) throw ValidationException::withMessages(['billing_date' => 'Payment already recorded for this billing date.']);
    //     return DB::transaction(function () use ($r, $s, $h, $d) {
    //         $expense = Expense::create(['household_id' => $h, 'user_id' => $r->user()->id, 'subscription_id' => $s->id, 'document_id' => $d['document_id'] ?? null, 'merchant_id' => $s->merchant_id, 'title' => $s->name . ' subscription', 'amount' => $d['amount'], 'currency' => $s->currency, 'expense_date' => $d['billing_date'], 'source' => 'subscription_confirmed']);
    //         $payment = $s->payments()->create(['expense_id' => $expense->id, 'amount' => $d['amount'], 'currency' => $s->currency, 'billing_date' => $d['billing_date'], 'source' => 'confirmed']);
    //         if ((float)$d['amount'] !== (float)$s->price) $s->update(['previous_price' => $s->price, 'price' => $d['amount'], 'last_price_change_at' => now()]);
    //         if ($d['billing_date'] >= $s->next_billing_date->toDateString()) {
    //             $next = $s->next_billing_date->toDateString();
    //             $i = 0;
    //             do {
    //                 $next = $this->calc->nextDate($next, $s->billing_cycle);
    //                 $i++;
    //             } while ($next <= $d['billing_date'] && $i < 120);
    //             $s->update(['next_billing_date' => $next, 'last_generated_billing_date' => null]);
    //             $this->reminders->subscription($s->fresh());
    //         }
    //         return ApiResponse::success(['payment' => $payment, 'expense' => $expense], 201);
    //     });
    // }
    public function recordPayment(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);

        $s = Subscription::where('household_id', $h)
            ->findOrFail($id);

        $d = $r->validate([
            'amount' => 'required|numeric|gt:0',
            'billing_date' => 'required|date',
            'document_id' => 'nullable|integer',
        ]);

        $this->access->requireDocument(
            $h,
            $d['document_id'] ?? null
        );

        /*
        |--------------------------------------------------------------------------
        | Normalize billing date
        |--------------------------------------------------------------------------
        |
        | subscription_payments.billing_date can be stored as a datetime value
        | such as:
        |
        | 2026-10-23 00:00:00
        |
        | while the API request normally contains:
        |
        | 2026-10-23
        |
        | whereDate() prevents the duplicate check from depending on the stored
        | time portion and works consistently with SQLite/PostgreSQL.
        |
        */

        $billingDate = \Carbon\Carbon::parse(
            $d['billing_date']
        )->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Duplicate payment protection
        |--------------------------------------------------------------------------
        */

        $paymentAlreadyExists = $s
            ->payments()
            ->whereDate(
                'billing_date',
                $billingDate
            )
            ->exists();

        if ($paymentAlreadyExists) {
            throw ValidationException::withMessages([
                'billing_date' =>
                    'Payment already recorded for this billing date.',
            ]);
        }

        return DB::transaction(
            function () use (
                $r,
                $s,
                $h,
                $d,
                $billingDate
            ) {
                /*
                |--------------------------------------------------------------------------
                | Create confirmed expense
                |--------------------------------------------------------------------------
                */

                $expense = Expense::create([
                    'household_id' => $h,
                    'user_id' => $r->user()->id,
                    'subscription_id' => $s->id,
                    'document_id' =>
                        $d['document_id'] ?? null,
                    'merchant_id' => $s->merchant_id,

                    'title' =>
                        $s->name . ' subscription',

                    'amount' => $d['amount'],
                    'currency' => $s->currency,
                    'expense_date' => $billingDate,
                    'source' =>
                        'subscription_confirmed',
                ]);

                /*
                |--------------------------------------------------------------------------
                | Record payment
                |--------------------------------------------------------------------------
                */

                $payment = $s
                    ->payments()
                    ->create([
                        'expense_id' => $expense->id,
                        'amount' => $d['amount'],
                        'currency' => $s->currency,
                        'billing_date' => $billingDate,
                        'source' => 'confirmed',
                    ]);

                /*
                |--------------------------------------------------------------------------
                | Detect price change
                |--------------------------------------------------------------------------
                */

                if (
                    (float) $d['amount'] !==
                    (float) $s->price
                ) {
                    $s->update([
                        'previous_price' =>
                            $s->price,

                        'price' =>
                            $d['amount'],

                        'last_price_change_at' =>
                            now(),
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Advance billing cycle
                |--------------------------------------------------------------------------
                */

                if (
                    $billingDate >=
                    $s->next_billing_date
                        ->toDateString()
                ) {
                    $next = $s
                        ->next_billing_date
                        ->toDateString();

                    $i = 0;

                    do {
                        $next = $this
                            ->calc
                            ->nextDate(
                                $next,
                                $s->billing_cycle
                            );

                        $i++;
                    } while (
                        $next <= $billingDate &&
                        $i < 120
                    );

                    $s->update([
                        'next_billing_date' =>
                            $next,

                        'last_generated_billing_date' =>
                            null,
                    ]);

                    $this
                        ->reminders
                        ->subscription(
                            $s->fresh()
                        );
                }

                return ApiResponse::success(
                    [
                        'payment' => $payment,
                        'expense' => $expense,
                    ],
                    201
                );
            }
        );
    }
    public function insights(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        $subs = Subscription::where('household_id', $h)->where('status', 'active')->get();
        $renewals = $subs->filter(fn($s) => $s->next_billing_date->between(today(), today()->addDays(30)))->values();
        $changed = $subs->filter(fn($s) => $s->previous_price !== null && (float)$s->price !== (float)$s->previous_price)->values();
        $duplicateGroups = $subs->groupBy('dedupe_key')->filter(fn($group) => $group->count() > 1)->values();
        // Pattern candidates only. Never create or cancel a subscription based on inferred transactions.
        $candidates = Expense::accessibleTo($r->user())->where('household_id', $h)->whereNotNull('merchant_id')->whereNull('subscription_id')->select('merchant_id', 'currency')->selectRaw('COUNT(*) as occurrence_count')->selectRaw('MIN(expense_date) as first_seen')->selectRaw('MAX(expense_date) as last_seen')->groupBy('merchant_id', 'currency')->havingRaw('COUNT(*) >= 2')->with('merchant')->get();
        return ApiResponse::success(['upcoming_renewals' => $renewals, 'price_changes' => $changed, 'duplicate_subscription_groups' => $duplicateGroups, 'recurring_charge_candidates' => $candidates, 'notice' => 'Recurring charge patterns are suggestions only. No subscription is created or cancelled automatically.']);
    }
}
