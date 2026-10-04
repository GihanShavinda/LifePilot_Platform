<?php

namespace App\Domain\Completion\Controllers;

use App\Domain\Completion\Models\EvaluationRun;
use App\Domain\Completion\Services\{CompletionAccess, EvaluationService, ReportExportService, SecurityReviewService};
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EvaluationController extends Controller
{
    public function __construct(
        private readonly CompletionAccess $access,
        private readonly EvaluationService $evaluation,
        private readonly SecurityReviewService $security,
        private readonly ReportExportService $exports,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::success($this->evaluation->summary($request->user()));
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:160']]);
        $run = $this->evaluation->run($request->user(), $data['label'] ?? 'P12 final evaluation');
        return ApiResponse::success(['run' => $this->evaluation->serializeRun($run)], 201);
    }

    public function runs(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());
        $runs = EvaluationRun::query()
            ->where('household_id', $householdId)
            ->where('user_id', $request->user()->id)
            ->with('metrics')
            ->latest('generated_at')
            ->paginate(20);

        return ApiResponse::success([
            'runs' => collect($runs->items())->map(fn (EvaluationRun $run) => $this->evaluation->serializeRun($run))->all(),
            'pagination' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function security(Request $request): JsonResponse
    {
        return ApiResponse::success(['checks' => $this->security->review($request->user())]);
    }

    public function export(Request $request, string $format): SymfonyResponse
    {
        return match (strtolower($format)) {
            'json' => response($this->exports->json($request->user()), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => 'attachment; filename="lifepilot-p12-evaluation.json"',
            ]),
            'csv' => response($this->exports->csv($request->user()), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="lifepilot-p12-evaluation.csv"',
            ]),
            'pdf' => response($this->exports->pdf($request->user()), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="lifepilot-p12-evaluation.pdf"',
            ]),
            default => response()->json([
                'success' => false,
                'message' => 'Unsupported export format. Use json, csv or pdf.',
            ], 422),
        };
    }
}
