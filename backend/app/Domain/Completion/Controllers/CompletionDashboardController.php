<?php

namespace App\Domain\Completion\Controllers;

use App\Domain\Completion\Services\{AiDashboardService, CompletionDashboardService, DocumentDashboardService, FinanceDashboardService, MainDashboardService};
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class CompletionDashboardController extends Controller
{
    public function __construct(
        private readonly CompletionDashboardService $all,
        private readonly MainDashboardService $main,
        private readonly DocumentDashboardService $documents,
        private readonly FinanceDashboardService $finance,
        private readonly AiDashboardService $ai,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($this->all->all($request->user(), $this->months($request)));
    }

    public function main(Request $request): JsonResponse
    {
        return ApiResponse::success($this->main->build($request->user()));
    }

    public function documents(Request $request): JsonResponse
    {
        return ApiResponse::success($this->documents->build($request->user()));
    }

    public function finance(Request $request): JsonResponse
    {
        return ApiResponse::success($this->finance->build($request->user(), $this->months($request)));
    }

    public function ai(Request $request): JsonResponse
    {
        return ApiResponse::success($this->ai->build($request->user()));
    }

    private function months(Request $request): int
    {
        return max(3, min(24, (int) $request->integer('months', 6)));
    }
}
