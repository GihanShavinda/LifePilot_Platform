<?php

namespace App\Domain\Actions\Controllers;

use App\Domain\Actions\Models\ActionPlan;
use App\Domain\Actions\Services\{
    ActionAccess,
    ActionApprovalService,
    ActionExecutor,
    ActionPlanService,
    AssistantActionPlanFactory
};
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ActionPlanController extends Controller
{
    public function index(Request $request, ActionAccess $access)
    {
        $householdId = $access->householdId($request->user());

        $plans = ActionPlan::query()
            ->where('household_id', $householdId)
            ->where('user_id', $request->user()->id)
            ->with(['steps', 'executions.result'])
            ->latest()
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $plans]);
    }

    public function store(Request $request, ActionPlanService $service)
    {
        $validated = $request->validate([
            'request' => 'required|string|min:2|max:4000',
            'origin' => 'nullable|string|in:user,assistant',
            'source_conversation_message_id' => 'nullable|integer',
            'evidence_refs' => 'nullable|array|max:50',
            'evidence_refs.*' => 'string|max:150',
            'idempotency_key' => 'nullable|string|max:64',
            'metadata' => 'nullable|array',
            'steps' => 'required|array|min:1|max:20',
            'steps.*.action_type' => 'required|string|max:80',
            'steps.*.title' => 'nullable|string|max:255',
            'steps.*.description' => 'nullable|string|max:2000',
            'steps.*.payload' => 'required|array',
            'steps.*.evidence_refs' => 'nullable|array|max:50',
            'steps.*.evidence_refs.*' => 'string|max:150',
        ]);

        $plan = $service->create($request->user(), $validated);

        return response()->json([
            'success' => true,
            'data' => ['plan' => $plan->load('steps')],
        ], 201);
    }

    public function show(Request $request, int $id, ActionAccess $access)
    {
        return response()->json([
            'success' => true,
            'data' => ['plan' => $access->plan($request->user(), $id)],
        ]);
    }

    public function approve(Request $request, int $id, ActionAccess $access, ActionApprovalService $approvals)
    {
        $validated = $request->validate([
            'step_ids' => 'nullable|array',
            'step_ids.*' => 'integer',
            'high_risk_ack' => 'nullable|boolean',
            'comment' => 'nullable|string|max:2000',
        ]);

        $plan = $access->plan($request->user(), $id);
        $plan = $approvals->approve($request->user(), $plan, $validated);

        return response()->json(['success' => true, 'data' => ['plan' => $plan]]);
    }

    public function execute(Request $request, int $id, ActionAccess $access, ActionExecutor $executor)
    {
        $plan = $access->plan($request->user(), $id);
        $plan = $executor->execute($request->user(), $plan);

        return response()->json(['success' => true, 'data' => ['plan' => $plan]]);
    }

    public function cancel(Request $request, int $id, ActionAccess $access)
    {
        $plan = $access->plan($request->user(), $id);
        abort_if(in_array($plan->status->value, ['executing', 'completed'], true), 422, 'Executing or completed plans cannot be cancelled.');
        $plan->update(['status' => 'cancelled']);
        return response()->json(['success' => true, 'data' => ['plan' => $plan->fresh('steps')]]);
    }

    public function audit(Request $request, int $id, ActionAccess $access)
    {
        $plan = $access->plan($request->user(), $id);

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $plan,
                'approvals' => $plan->approvals()->with('step')->oldest()->get(),
                'executions' => $plan->executions()->with(['step', 'result'])->oldest()->get(),
            ],
        ]);
    }

    public function fromAssistant(Request $request, int $messageId, AssistantActionPlanFactory $factory)
    {
        $plan = $factory->fromMessage($request->user(), $messageId);
        return response()->json(['success' => true, 'data' => ['plan' => $plan->load('steps')]], 201);
    }
}
