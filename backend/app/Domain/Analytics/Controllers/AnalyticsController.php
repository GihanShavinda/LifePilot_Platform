<?php

namespace App\Domain\Analytics\Controllers;

use App\Domain\Analytics\Models\{AnalyticsInsight, DataQualityCheck, PredictionRecord};
use App\Domain\Analytics\Services\{AnalyticsAccess, AnalyticsService, DataQualityService, InsightService, PredictionService};
use App\Support\ApiResponse;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Routing\Controller;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsAccess $access,
        private readonly AnalyticsService $analytics,
        private readonly PredictionService $predictions,
        private readonly DataQualityService $quality,
        private readonly InsightService $insights,
    ) {
    }

    public function dashboard(Request $request): JsonResponse
    {
        $months = (int) $request->integer('months', 6);
        $months = max(3, min(24, $months));
        $user = $request->user();
        $householdId = $this->access->householdId($user);

        return ApiResponse::success([
            'analytics' => $this->analytics->dashboard($user, $months),
            'predictions' => PredictionRecord::query()
                ->where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->latest('generated_at')
                ->get()
                ->unique('prediction_type')
                ->values(),
            'insights' => AnalyticsInsight::query()
                ->where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->latest('generated_at')
                ->get(),
            'data_quality' => DataQualityCheck::query()
                ->where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->latest('generated_at')
                ->get(),
            'needs_refresh' => !PredictionRecord::query()
                ->where('household_id', $householdId)
                ->where('user_id', $user->id)
                ->exists(),
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $predictions = $this->predictions->refresh($user);
        $quality = $this->quality->refresh($user);
        $insights = $this->insights->refresh($user);

        return ApiResponse::success([
            'predictions' => $predictions,
            'insights' => $insights,
            'data_quality' => $quality,
            'model_version' => PredictionService::MODEL_VERSION,
            'feature_version' => PredictionService::FEATURE_VERSION,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function predictions(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());

        return ApiResponse::success([
            'predictions' => PredictionRecord::query()
                ->where('household_id', $householdId)
                ->where('user_id', $request->user()->id)
                ->latest('generated_at')
                ->paginate(50),
        ]);
    }

    public function insights(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());

        return ApiResponse::success([
            'insights' => AnalyticsInsight::query()
                ->where('household_id', $householdId)
                ->where('user_id', $request->user()->id)
                ->latest('generated_at')
                ->get(),
        ]);
    }

    public function dataQuality(Request $request): JsonResponse
    {
        $householdId = $this->access->householdId($request->user());

        return ApiResponse::success([
            'checks' => DataQualityCheck::query()
                ->where('household_id', $householdId)
                ->where('user_id', $request->user()->id)
                ->orderByRaw("CASE status WHEN 'warning' THEN 0 WHEN 'info' THEN 1 ELSE 2 END")
                ->orderBy('check_key')
                ->get(),
        ]);
    }
}
