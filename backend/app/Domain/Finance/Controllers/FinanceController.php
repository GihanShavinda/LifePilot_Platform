<?php

namespace App\Domain\Finance\Controllers;

use App\Domain\Finance\Models\{Expense, ExpenseCategory, Merchant, Subscription, SubscriptionPayment, Asset, Warranty};
use App\Domain\Finance\Services\{FinanceAccess, FinanceCalculations, ReceiptExpenseService};
use App\Domain\Documents\Models\Document;
use App\Support\ApiResponse;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FinanceController
{
    public function __construct(private FinanceAccess $access, private FinanceCalculations $calc) {}
    private function expenseRules(bool $create = true): array
    {
        return ['title' => ($create ? 'required' : 'sometimes') . '|string|max:255', 'amount' => ($create ? 'required' : 'sometimes') . '|numeric|gt:0', 'currency' => ($create ? 'required' : 'sometimes') . '|string|size:3', 'expense_date' => ($create ? 'required' : 'sometimes') . '|date', 'description' => 'nullable|string|max:5000', 'expense_category_id' => 'nullable|integer', 'merchant_name' => 'nullable|string|max:255', 'document_id' => 'nullable|integer', 'recurrence_cycle' => 'nullable|in:daily,weekly,monthly,quarterly,yearly', 'next_occurrence_date' => 'nullable|date'];
    }
    private function prepare(Request $r, array $data, int $h): array
    {
        $this->access->requireDocument($h, $data['document_id'] ?? null, $r->user());
        if (!empty($data['expense_category_id'])) ExpenseCategory::where('household_id', $h)->findOrFail($data['expense_category_id']);
        if (!empty($data['merchant_name'])) {
            $name = trim($data['merchant_name']);
            $merchant = Merchant::firstOrCreate(['household_id' => $h, 'normalized_name' => mb_strtolower($name)], ['name' => $name]);
            $data['merchant_id'] = $merchant->id;
        }
        unset($data['merchant_name']);
        if (isset($data['currency'])) $data['currency'] = strtoupper($data['currency']);
        if (!empty($data['recurrence_cycle']) && !isset($data['next_occurrence_date'])) $data['next_occurrence_date'] = $this->calc->nextDate($data['expense_date'] ?? today()->toDateString(), $data['recurrence_cycle']);
        if (array_key_exists('recurrence_cycle', $data) && !$data['recurrence_cycle']) $data['next_occurrence_date'] = null;
        return $data;
    }
    public function expenses(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        $q = Expense::accessibleTo($r->user())->with(['category', 'merchant'])->where('household_id', $h);
        foreach (['currency', 'merchant_id', 'expense_category_id'] as $f) if ($r->filled($f)) $q->where($f, $r->query($f));
        if ($r->filled('from')) $q->whereDate('expense_date', '>=', $r->query('from'));
        if ($r->filled('to')) $q->whereDate('expense_date', '<=', $r->query('to'));
        return ApiResponse::success(['expenses' => $q->orderByDesc('expense_date')->paginate(40)]);
    }
    public function createExpense(Request $r): JsonResponse
    {
        $h = $this->access->household($r, true);
        $data = $this->prepare($r, $r->validate($this->expenseRules()), $h);
        return ApiResponse::success(['expense' => Expense::create(array_merge($data, ['household_id' => $h, 'user_id' => $r->user()->id, 'source' => 'manual']))->load(['category', 'merchant'])], 201);
    }
    public function updateExpense(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $expense = Expense::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $expense, true);
        // Reviewed receipts are editable manually without changing original extraction.
        $data = $this->prepare($r, $r->validate($this->expenseRules(false)), $h);
        $expense->update($data);
        return ApiResponse::success(['expense' => $expense->fresh(['category', 'merchant'])]);
    }
    public function deleteExpense(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $expense = Expense::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $expense, true);
        $expense->delete();
        return ApiResponse::success(['deleted' => true]);
    }
    public function categories(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        return ApiResponse::success(['categories' => ExpenseCategory::where('household_id', $h)->orderBy('name')->get(), 'merchants' => Merchant::where('household_id', $h)->orderBy('name')->get()]);
    }
    public function saveCategory(Request $r): JsonResponse
    {
        $h = $this->access->household($r, true);
        $data = $r->validate(['name' => 'required|string|max:100', 'monthly_budget' => 'nullable|numeric|min:0']);
        $slug = \Illuminate\Support\Str::slug($data['name']);
        return ApiResponse::success(['category' => ExpenseCategory::updateOrCreate(['household_id' => $h, 'slug' => $slug], ['name' => $data['name'], 'monthly_budget' => $data['monthly_budget'] ?? null])]);
    }
    public function receiptPreview(Request $r, int $id, ReceiptExpenseService $service): JsonResponse
    {
        $h = $this->access->household($r);
        $doc = Document::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        return ApiResponse::success(['suggestion' => $service->preview($doc)]);
    }
    public function acceptReceipt(Request $r, int $id, ReceiptExpenseService $service): JsonResponse
    {
        $h = $this->access->household($r, true);
        $doc = Document::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $doc, true);
        return ApiResponse::success(['expense' => $service->accept($doc, $r->user()->id)], 201);
    }
    public function dashboard(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        $month = $r->query('month', today()->format('Y-m'));
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) throw ValidationException::withMessages(['month' => 'Expected YYYY-MM.']);
        $start = $month . '-01';
        $end = \Carbon\CarbonImmutable::parse($start)->endOfMonth()->toDateString();
        $expenses = Expense::accessibleTo($r->user())->where('household_id', $h)->whereBetween('expense_date', [$start, $end]);
        // Do not sum different currencies into one misleading number.
        $monthly = (clone $expenses)->select('currency')->selectRaw('SUM(amount) as total')->groupBy('currency')->get();
        $merchants = (clone $expenses)->select('merchant_id', 'currency')->selectRaw('SUM(amount) as total')->groupBy('merchant_id', 'currency')->with('merchant')->get();
        $subscriptions = Subscription::where('household_id', $h)->where('user_id', $r->user()->id)->where('status', 'active')->get();
        $recurring = $subscriptions->groupBy('currency')->map(fn($items) => $items->reduce(fn($total, $s) => $total + (float)$this->calc->monthlyEquivalent((string)$s->price, $s->billing_cycle), 0));
        $due = $subscriptions->filter(fn($s) => $s->next_billing_date->between(today(), today()->addDays(30)))->values();
        $visibleAssetIds = Asset::accessibleTo($r->user())->where('household_id', $h)->pluck('id');
        $warranties = Warranty::where('household_id', $h)->whereIn('asset_id', $visibleAssetIds)->whereBetween('end_date', [today()->toDateString(), today()->addDays(30)->toDateString()])->with('asset:id,name')->get();
        return ApiResponse::success(['monthly_totals' => $monthly, 'merchant_totals' => $merchants, 'monthly_subscription_cost_by_currency' => $recurring, 'subscriptions_due' => $due, 'warranties_expiring' => $warranties, 'recent_purchases' => Asset::accessibleTo($r->user())->where('household_id', $h)->orderByDesc('purchase_date')->limit(10)->get(), 'asset_value_placeholder' => ['status' => 'not_valued', 'note' => 'Purchase prices are not current market valuations.']]);
    }
}
