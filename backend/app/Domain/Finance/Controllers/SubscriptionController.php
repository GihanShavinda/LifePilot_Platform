<?php

namespace App\Domain\Finance\Controllers;

use App\Domain\Finance\Models\{
    Subscription,
    Expense,
    Merchant
};
use App\Domain\Finance\Services\{
    FinanceAccess,
    LifeFinanceReminderService,
    FinanceCalculations
};
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionController
{
    public function __construct(
        private FinanceAccess $access,
        private LifeFinanceReminderService $reminders,
        private FinanceCalculations $calc
    ) {}

    private function rules(bool $create = true): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'name' => "$required|string|max:255",
            'provider' => "$required|string|max:255",
            'price' => "$required|numeric|gt:0",
            'currency' => "$required|string|size:3",
            'billing_cycle' =>
                "$required|in:daily,weekly,monthly,quarterly,yearly",
            'next_billing_date' => "$required|date",
            'renewal_type' => 'sometimes|in:automatic,manual',
            'cancellation_deadline' => 'nullable|date',
            'status' => 'sometimes|in:active,paused,cancelled',
            'document_id' => 'nullable|integer',
        ];
    }

    private function key(
        string $provider,
        string $name
    ): string {
        $provider = mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($provider))
        );

        $name = mb_strtolower(
            preg_replace('/\s+/u', ' ', trim($name))
        );

        return hash(
            'sha256',
            $provider . '|' . $name
        );
    }

    private function prepare(
        array $data,
        int $householdId
    ): array {
        $this->access->requireDocument(
            $householdId,
            $data['document_id'] ?? null
        );

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper(
                $data['currency']
            );
        }

        if (
            isset($data['name']) &&
            isset($data['provider'])
        ) {
            $data['dedupe_key'] = $this->key(
                $data['provider'],
                $data['name']
            );
        }

        if (isset($data['provider'])) {
            $name = trim($data['provider']);

            $merchant = Merchant::firstOrCreate(
                [
                    'household_id' => $householdId,
                    'normalized_name' => mb_strtolower($name),
                ],
                [
                    'name' => $name,
                ]
            );

            $data['merchant_id'] = $merchant->id;
        }

        if (
            !empty($data['cancellation_deadline']) &&
            !empty($data['next_billing_date']) &&
            CarbonImmutable::parse(
                $data['cancellation_deadline']
            )->greaterThan(
                CarbonImmutable::parse(
                    $data['next_billing_date']
                )
            )
        ) {
            throw ValidationException::withMessages([
                'cancellation_deadline' =>
                    'Cancellation deadline must not follow the next billing date.',
            ]);
        }

        return $data;
    }

    public function index(Request $request): JsonResponse
    {
        $householdId = $this->access->household($request);

        return ApiResponse::success([
            'subscriptions' => Subscription::query()
                ->where('household_id', $householdId)
                ->with('payments')
                ->orderBy('next_billing_date')
                ->paginate(50),
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        $householdId = $this->access->household(
            $request,
            true
        );

        $data = $this->prepare(
            $request->validate($this->rules()),
            $householdId
        );

        $exists = Subscription::withTrashed()
            ->where('household_id', $householdId)
            ->where('dedupe_key', $data['dedupe_key'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' =>
                    'A subscription with this provider and name already exists.',
            ]);
        }

        return DB::transaction(function () use (
            $request,
            $householdId,
            $data
        ) {
            $subscription = Subscription::create(
                array_merge(
                    $data,
                    [
                        'household_id' => $householdId,
                        'user_id' => $request->user()->id,
                    ]
                )
            );

            $this->reminders->subscription($subscription);

            return ApiResponse::success([
                'subscription' => $subscription->fresh(),
            ], 201);
        });
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $subscription = Subscription::query()
            ->where('household_id', $householdId)
            ->findOrFail($id);

        $data = $request->validate(
            $this->rules(false)
        );

        $data = $this->prepare(
            array_merge(
                [
                    'name' => $subscription->name,
                    'provider' => $subscription->provider,
                    'next_billing_date' =>
                        $subscription->next_billing_date
                            ->toDateString(),
                ],
                $data
            ),
            $householdId
        );

        $duplicate = Subscription::withTrashed()
            ->where('household_id', $householdId)
            ->where('dedupe_key', $data['dedupe_key'])
            ->where('id', '!=', $subscription->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Duplicate subscription.',
            ]);
        }

        if (
            isset($data['price']) &&
            (float) $data['price'] !==
                (float) $subscription->price
        ) {
            $data['previous_price'] =
                $subscription->price;

            $data['last_price_change_at'] = now();
        }

        if (
            isset($data['next_billing_date']) &&
            $data['next_billing_date'] !==
                $subscription->next_billing_date
                    ->toDateString()
        ) {
            $data['last_generated_billing_date'] = null;
        }

        $subscription->update($data);

        if ($subscription->status !== 'active') {
            $this->reminders->cancelSubscriptionTask(
                $subscription
            );
        } else {
            $this->reminders->subscription(
                $subscription->fresh()
            );
        }

        return ApiResponse::success([
            'subscription' => $subscription->fresh(),
        ]);
    }

    public function remove(
        Request $request,
        int $id
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $subscription = Subscription::query()
            ->where('household_id', $householdId)
            ->findOrFail($id);

        $this->reminders->cancelSubscriptionTask(
            $subscription
        );

        $subscription->update([
            'status' => 'cancelled',
        ]);

        return ApiResponse::success([
            'subscription' => $subscription->fresh(),
            'note' =>
                'Tracking changed; no external subscription has been cancelled.',
        ]);
    }

    public function recordPayment(
        Request $request,
        int $id
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'billing_date' => 'required|date',
            'document_id' => 'nullable|integer',
        ]);

        $this->access->requireDocument(
            $householdId,
            $data['document_id'] ?? null
        );

        /*
         * Normalize date input before comparing it
         * against database values.
         */
        $billingDate = CarbonImmutable::parse(
            $data['billing_date']
        )->toDateString();

        try {
            return DB::transaction(function () use (
                $request,
                $householdId,
                $id,
                $data,
                $billingDate
            ) {
                /*
                 * Lock the subscription row on databases
                 * that support row-level locks.
                 */
                $subscription = Subscription::query()
                    ->where('household_id', $householdId)
                    ->lockForUpdate()
                    ->findOrFail($id);

                /*
                 * Use whereDate, not where.
                 *
                 * A DATETIME value such as:
                 * 2026-10-20 00:00:00
                 *
                 * must match the input:
                 * 2026-10-20
                 */
                $alreadyRecorded = $subscription
                    ->payments()
                    ->whereDate(
                        'billing_date',
                        $billingDate
                    )
                    ->exists();

                if ($alreadyRecorded) {
                    throw ValidationException::withMessages([
                        'billing_date' =>
                            'A payment has already been recorded for this billing date.',
                    ]);
                }

                /*
                 * Create the associated expense only
                 * after duplicate validation.
                 */
                $expense = Expense::create([
                    'household_id' => $householdId,
                    'user_id' => $request->user()->id,
                    'subscription_id' => $subscription->id,
                    'document_id' =>
                        $data['document_id'] ?? null,
                    'merchant_id' =>
                        $subscription->merchant_id,
                    'title' =>
                        $subscription->name . ' subscription',
                    'amount' => $data['amount'],
                    'currency' => $subscription->currency,
                    'expense_date' => $billingDate,
                    'source' => 'subscription_confirmed',
                ]);

                $payment = $subscription
                    ->payments()
                    ->create([
                        'expense_id' => $expense->id,
                        'amount' => $data['amount'],
                        'currency' => $subscription->currency,
                        'billing_date' => $billingDate,
                        'source' => 'confirmed',
                    ]);

                /*
                 * Track price changes.
                 */
                if (
                    (float) $data['amount'] !==
                        (float) $subscription->price
                ) {
                    $subscription->update([
                        'previous_price' =>
                            $subscription->price,
                        'price' => $data['amount'],
                        'last_price_change_at' => now(),
                    ]);
                }

                /*
                 * Advance billing cycle only when
                 * the confirmed payment covers the
                 * current or a later billing date.
                 */
                if (
                    $billingDate >=
                    $subscription->next_billing_date
                        ->toDateString()
                ) {
                    $next = $subscription
                        ->next_billing_date
                        ->toDateString();

                    $iterations = 0;

                    do {
                        $next = $this->calc->nextDate(
                            $next,
                            $subscription->billing_cycle
                        );

                        $iterations++;
                    } while (
                        $next <= $billingDate &&
                        $iterations < 120
                    );

                    if ($next <= $billingDate) {
                        throw ValidationException::withMessages([
                            'billing_date' =>
                                'Unable to calculate the next billing date.',
                        ]);
                    }

                    $subscription->update([
                        'next_billing_date' => $next,
                        'last_generated_billing_date' => null,
                    ]);

                    $this->reminders->subscription(
                        $subscription->fresh()
                    );
                }

                return ApiResponse::success([
                    'payment' => $payment,
                    'expense' => $expense,
                ], 201);
            });
        } catch (UniqueConstraintViolationException $exception) {
            /*
             * Concurrent requests can pass the initial
             * duplicate check. Retain the database unique
             * constraint and translate this specific
             * duplicate into a validation error.
             */
            $duplicateExists = Subscription::query()
                ->where('household_id', $householdId)
                ->whereKey($id)
                ->whereHas(
                    'payments',
                    fn ($query) => $query->whereDate(
                        'billing_date',
                        $billingDate
                    )
                )
                ->exists();

            if ($duplicateExists) {
                throw ValidationException::withMessages([
                    'billing_date' =>
                        'A payment has already been recorded for this billing date.',
                ]);
            }

            throw $exception;
        }
    }

    public function insights(Request $request): JsonResponse
    {
        $householdId = $this->access->household($request);

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->get();

        $renewals = $subscriptions
            ->filter(
                fn ($subscription) =>
                    $subscription->next_billing_date->between(
                        today(),
                        today()->addDays(30)
                    )
            )
            ->values();

        $changed = $subscriptions
            ->filter(
                fn ($subscription) =>
                    $subscription->previous_price !== null &&
                    (float) $subscription->price !==
                        (float) $subscription->previous_price
            )
            ->values();

        $duplicateGroups = $subscriptions
            ->groupBy('dedupe_key')
            ->filter(
                fn ($group) => $group->count() > 1
            )
            ->values();

        /*
         * Recurring-charge candidates are informational.
         * Never create or cancel subscriptions automatically.
         */
        $candidates = Expense::query()
            ->where('household_id', $householdId)
            ->whereNotNull('merchant_id')
            ->whereNull('subscription_id')
            ->select('merchant_id', 'currency')
            ->selectRaw('COUNT(*) as occurrence_count')
            ->selectRaw('MIN(expense_date) as first_seen')
            ->selectRaw('MAX(expense_date) as last_seen')
            ->groupBy('merchant_id', 'currency')
            ->havingRaw('COUNT(*) >= 2')
            ->with('merchant')
            ->get();

        return ApiResponse::success([
            'upcoming_renewals' => $renewals,
            'price_changes' => $changed,
            'duplicate_subscription_groups' =>
                $duplicateGroups,
            'recurring_charge_candidates' => $candidates,
            'notice' =>
                'Recurring charge patterns are suggestions only. ' .
                'No subscription is created or cancelled automatically.',
        ]);
    }
}