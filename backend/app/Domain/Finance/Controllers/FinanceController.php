<?php

namespace App\Domain\Finance\Controllers;

use App\Domain\Finance\Models\{
    Expense,
    ExpenseCategory,
    Merchant,
    Subscription,
    Asset,
    Warranty
};
use App\Domain\Finance\Services\{
    FinanceAccess,
    FinanceCalculations,
    ReceiptExpenseService
};
use App\Domain\Documents\Models\Document;
use App\Support\ApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class FinanceController
{
    public function __construct(
        private FinanceAccess $access,
        private FinanceCalculations $calc
    ) {}

    private function expenseRules(bool $create = true): array
    {
        $required = $create ? 'required' : 'sometimes';

        return [
            'title' => "$required|string|max:255",
            'amount' => "$required|numeric|gt:0",
            'currency' => "$required|string|size:3",
            'expense_date' => "$required|date",
            'description' => 'nullable|string|max:5000',
            'expense_category_id' => 'nullable|integer',
            'merchant_name' => 'nullable|string|max:255',
            'document_id' => 'nullable|integer',
            'recurrence_cycle' =>
                'nullable|in:daily,weekly,monthly,quarterly,yearly',
            'next_occurrence_date' => 'nullable|date',
        ];
    }

    private function prepare(
        Request $request,
        array $data,
        int $householdId
    ): array {
        $this->access->requireDocument(
            $householdId,
            $data['document_id'] ?? null
        );

        if (!empty($data['expense_category_id'])) {
            ExpenseCategory::where('household_id', $householdId)
                ->findOrFail($data['expense_category_id']);
        }

        if (!empty($data['merchant_name'])) {
            $name = trim($data['merchant_name']);

            $merchant = Merchant::firstOrCreate(
                [
                    'household_id' => $householdId,
                    'normalized_name' => mb_strtolower($name),
                ],
                ['name' => $name]
            );

            $data['merchant_id'] = $merchant->id;
        }

        unset($data['merchant_name']);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        if (
            !empty($data['recurrence_cycle']) &&
            !isset($data['next_occurrence_date'])
        ) {
            $date = $data['expense_date']
                ?? today()->toDateString();

            $data['next_occurrence_date'] =
                $this->calc->nextDate(
                    $date,
                    $data['recurrence_cycle']
                );
        }

        if (
            array_key_exists('recurrence_cycle', $data) &&
            !$data['recurrence_cycle']
        ) {
            $data['next_occurrence_date'] = null;
        }

        return $data;
    }

    public function expenses(Request $request): JsonResponse
    {
        $householdId = $this->access->household($request);

        $query = Expense::with(['category', 'merchant'])
            ->where('household_id', $householdId);

        foreach (
            ['currency', 'merchant_id', 'expense_category_id']
            as $field
        ) {
            if ($request->filled($field)) {
                $query->where($field, $request->query($field));
            }
        }

        if ($request->filled('from')) {
            $query->whereDate(
                'expense_date',
                '>=',
                $request->query('from')
            );
        }

        if ($request->filled('to')) {
            $query->whereDate(
                'expense_date',
                '<=',
                $request->query('to')
            );
        }

        return ApiResponse::success([
            'expenses' => $query
                ->orderByDesc('expense_date')
                ->paginate(40),
        ]);
    }

    public function createExpense(Request $request): JsonResponse
    {
        $householdId = $this->access->household(
            $request,
            true
        );

        $data = $this->prepare(
            $request,
            $request->validate($this->expenseRules()),
            $householdId
        );

        $expense = Expense::create(
            array_merge(
                $data,
                [
                    'household_id' => $householdId,
                    'user_id' => $request->user()->id,
                    'source' => 'manual',
                ]
            )
        );

        return ApiResponse::success([
            'expense' => $expense->load([
                'category',
                'merchant',
            ]),
        ], 201);
    }

    public function updateExpense(
        Request $request,
        int $id
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $expense = Expense::where(
            'household_id',
            $householdId
        )->findOrFail($id);

        $data = $this->prepare(
            $request,
            $request->validate($this->expenseRules(false)),
            $householdId
        );

        $expense->update($data);

        return ApiResponse::success([
            'expense' => $expense->fresh([
                'category',
                'merchant',
            ]),
        ]);
    }

    public function deleteExpense(
        Request $request,
        int $id
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        Expense::where(
            'household_id',
            $householdId
        )->findOrFail($id)->delete();

        return ApiResponse::success([
            'deleted' => true,
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $householdId = $this->access->household($request);

        return ApiResponse::success([
            'categories' => ExpenseCategory::where(
                'household_id',
                $householdId
            )->orderBy('name')->get(),

            'merchants' => Merchant::where(
                'household_id',
                $householdId
            )->orderBy('name')->get(),
        ]);
    }

    public function saveCategory(
        Request $request
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'monthly_budget' => 'nullable|numeric|min:0',
        ]);

        $slug = Str::slug($data['name']);

        $category = ExpenseCategory::updateOrCreate(
            [
                'household_id' => $householdId,
                'slug' => $slug,
            ],
            [
                'name' => $data['name'],
                'monthly_budget' =>
                    $data['monthly_budget'] ?? null,
            ]
        );

        return ApiResponse::success([
            'category' => $category,
        ]);
    }

    public function receiptPreview(
        Request $request,
        int $id,
        ReceiptExpenseService $service
    ): JsonResponse {
        $householdId = $this->access->household($request);

        $document = Document::where(
            'household_id',
            $householdId
        )->findOrFail($id);

        return ApiResponse::success([
            'suggestion' => $service->preview($document),
        ]);
    }

    public function acceptReceipt(
        Request $request,
        int $id,
        ReceiptExpenseService $service
    ): JsonResponse {
        $householdId = $this->access->household(
            $request,
            true
        );

        $document = Document::where(
            'household_id',
            $householdId
        )->findOrFail($id);

        $expense = $service->accept(
            $document,
            $request->user()->id
        );

        return ApiResponse::success([
            'expense' => $expense,
        ], 201);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $householdId = $this->access->household($request);

        $month = $request->query(
            'month',
            today()->format('Y-m')
        );

        if (
            !is_string($month) ||
            !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
        ) {
            throw ValidationException::withMessages([
                'month' => 'Expected YYYY-MM.',
            ]);
        }

        $start = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $month . '-01'
        );

        $end = $start->endOfMonth();

        /*
         * Use calendar-date comparisons.
         *
         * This handles both DATE and DATETIME storage
         * on PostgreSQL and SQLite.
         */
        $expenses = Expense::query()
            ->where('household_id', $householdId)
            ->whereDate(
                'expense_date',
                '>=',
                $start->toDateString()
            )
            ->whereDate(
                'expense_date',
                '<=',
                $end->toDateString()
            );

        /*
         * Never combine different currencies.
         */
        $monthly = (clone $expenses)
            ->select('currency')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $merchants = (clone $expenses)
            ->select('merchant_id', 'currency')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('merchant_id', 'currency')
            ->with('merchant')
            ->get();

        $subscriptions = Subscription::query()
            ->where('household_id', $householdId)
            ->where('status', 'active')
            ->get();

        $recurring = $subscriptions
            ->groupBy('currency')
            ->map(
                fn ($items) => $items->reduce(
                    fn ($total, $subscription) =>
                        $total + (float) $this->calc
                            ->monthlyEquivalent(
                                (string) $subscription->price,
                                $subscription->billing_cycle
                            ),
                    0
                )
            );

        $due = $subscriptions
            ->filter(
                fn ($subscription) =>
                    $subscription->next_billing_date->between(
                        today(),
                        today()->addDays(30)
                    )
            )
            ->values();

        $warranties = Warranty::query()
            ->where('household_id', $householdId)
            ->whereDate(
                'end_date',
                '>=',
                today()->toDateString()
            )
            ->whereDate(
                'end_date',
                '<=',
                today()->addDays(30)->toDateString()
            )
            ->with('asset:id,name')
            ->get();

        $purchases = Asset::query()
            ->where('household_id', $householdId)
            ->orderByDesc('purchase_date')
            ->limit(10)
            ->get();

        return ApiResponse::success([
            'monthly_totals' => $monthly,
            'merchant_totals' => $merchants,
            'monthly_subscription_cost_by_currency' =>
                $recurring,
            'subscriptions_due' => $due,
            'warranties_expiring' => $warranties,
            'recent_purchases' => $purchases,
            'asset_value_placeholder' => [
                'status' => 'not_valued',
                'note' =>
                    'Purchase prices are not current market valuations.',
            ],
        ]);
    }
}