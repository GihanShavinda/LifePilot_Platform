<?php

namespace App\Domain\Finance\Controllers;

use App\Domain\Finance\Models\{Asset, AssetCategory, Merchant, Warranty, MaintenanceRecord};
use App\Domain\Finance\Services\{FinanceAccess, LifeFinanceReminderService, FinanceCalculations};
use App\Support\ApiResponse;
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetController
{
    public function __construct(private FinanceAccess $access, private LifeFinanceReminderService $reminders, private FinanceCalculations $calc) {}
    private function rules(bool $create): array
    {
        $req = $create ? 'required' : 'sometimes';
        return ['name' => $req . '|string|max:255', 'asset_category_id' => 'nullable|integer', 'merchant_id' => 'nullable|integer', 'document_id' => 'nullable|integer', 'brand' => 'nullable|string|max:150', 'model' => 'nullable|string|max:150', 'serial_number' => 'nullable|string|max:150', 'purchase_date' => 'nullable|date', 'purchase_price' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'location' => 'nullable|string|max:255', 'status' => 'sometimes|in:owned,sold,lost,disposed'];
    }
    private function validateRelated(int $h, array $d, Request $r): void
    {
        $this->access->requireDocument($h, $d['document_id'] ?? null, $r->user());
        if (!empty($d['asset_category_id'])) AssetCategory::where('household_id', $h)->findOrFail($d['asset_category_id']);
        if (!empty($d['merchant_id'])) Merchant::where('household_id', $h)->findOrFail($d['merchant_id']);
    }
    public function index(Request $r): JsonResponse
    {
        $h = $this->access->household($r);
        return ApiResponse::success(['assets' => Asset::accessibleTo($r->user())->where('household_id', $h)->with(['category', 'warranties', 'maintenance'])->latest()->paginate(40), 'categories' => AssetCategory::where('household_id', $h)->get()]);
    }
    public function category(Request $r): JsonResponse
    {
        $h = $this->access->household($r, true);
        $d = $r->validate(['name' => 'required|string|max:100']);
        return ApiResponse::success(['category' => AssetCategory::firstOrCreate(['household_id' => $h, 'slug' => \Illuminate\Support\Str::slug($d['name'])], ['name' => $d['name']])]);
    }
    public function create(Request $r): JsonResponse
    {
        $h = $this->access->household($r, true);
        $d = $r->validate($this->rules(true));
        $this->validateRelated($h, $d, $r);
        if (!empty($d['currency'])) $d['currency'] = strtoupper($d['currency']);
        return ApiResponse::success(['asset' => Asset::create(array_merge($d, ['household_id' => $h, 'user_id' => $r->user()->id]))], 201);
    }
    public function update(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $a = Asset::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $a, true);
        $d = $r->validate($this->rules(false));
        $this->validateRelated($h, $d, $r);
        $a->update($d);
        return ApiResponse::success(['asset' => $a->fresh()]);
    }
    public function remove(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $a = Asset::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $a, true);
        $a->delete();
        return ApiResponse::success(['deleted' => true]);
    }
    public function addWarranty(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $a = Asset::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $a, true);
        $d = $r->validate(['provider' => 'required|string|max:255', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date', 'coverage_notes' => 'nullable|string|max:5000', 'proof_document_id' => 'nullable|integer']);
        $this->access->requireDocument($h, $d['proof_document_id'] ?? null, $r->user());
        $d['status'] = $this->calc->warrantyState($d['end_date']);
        return DB::transaction(function () use ($d, $a, $r, $h) {
            $w = $a->warranties()->create(array_merge($d, ['household_id' => $h]));
            $this->reminders->warranty($w, $r->user()->id);
            return ApiResponse::success(['warranty' => $w->fresh()], 201);
        });
    }
    public function updateWarranty(Request $r, int $id, int $warrantyId): JsonResponse
    {
        $h = $this->access->household($r, true);
        $a = Asset::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $a, true);
        $w = $a->warranties()->findOrFail($warrantyId);
        $d = $r->validate(['provider' => 'sometimes|string|max:255', 'start_date' => 'sometimes|date', 'end_date' => 'sometimes|date', 'coverage_notes' => 'nullable|string|max:5000', 'proof_document_id' => 'nullable|integer', 'status' => 'sometimes|in:active,void']);
        $this->access->requireDocument($h, $d['proof_document_id'] ?? null, $r->user());
        if (isset($d['end_date']) && $d['end_date'] < ($d['start_date'] ?? $w->start_date->toDateString())) throw ValidationException::withMessages(['end_date' => 'Warranty cannot end before it starts.']);
        if (isset($d['end_date'])) {
            if ($w->reminder_task_id) {
                \App\Domain\Obligations\Models\Task::whereKey($w->reminder_task_id)->where('household_id', $h)->whereNotIn('status', ['completed', 'skipped'])->update(['status' => 'skipped']);
            }
            $d['reminder_task_id'] = null;
        }
        if (!isset($d['status'])) $d['status'] = $this->calc->warrantyState($d['end_date'] ?? $w->end_date->toDateString());
        $w->update($d);
        $this->reminders->warranty($w->fresh(), $r->user()->id);
        return ApiResponse::success(['warranty' => $w->fresh()]);
    }
    public function addMaintenance(Request $r, int $id): JsonResponse
    {
        $h = $this->access->household($r, true);
        $a = Asset::accessibleTo($r->user())->where('household_id', $h)->findOrFail($id);
        $this->access->ensureResource($r->user(), $a, true);
        $d = $r->validate(['title' => 'required|string|max:255', 'notes' => 'nullable|string|max:5000', 'performed_at' => 'nullable|date', 'next_due_date' => 'nullable|date|after:today', 'cost' => 'nullable|numeric|min:0', 'currency' => 'nullable|string|size:3', 'document_id' => 'nullable|integer']);
        $this->access->requireDocument($h, $d['document_id'] ?? null, $r->user());
        return DB::transaction(function () use ($a, $d, $h, $r) {
            $m = $a->maintenance()->create(array_merge($d, ['household_id' => $h]));
            $this->reminders->maintenance($m, $r->user()->id);
            return ApiResponse::success(['maintenance' => $m->fresh()], 201);
        });
    }
}
