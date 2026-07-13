<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder for a future Analytics milestone. No analytics/events
 * table exists yet — deliberately not built now, since a real
 * time-series schema should be designed against real requirements
 * (retention window, aggregation granularity), not guessed at as a
 * side effect of this foundation milestone.
 */
class AnalyticsController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: [
            'message' => 'Analytics integration is not yet implemented.',
        ]);
    }
}
