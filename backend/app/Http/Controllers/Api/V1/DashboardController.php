<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DashboardSummaryResource;
use App\Http\Support\ApiResponse;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

/**
 * The one real (non-placeholder) endpoint this milestone ships — see
 * docs/adr/0004-backend-foundation.md. Everything else under
 * /api/v1 is a placeholder for a later milestone; this one is queried
 * for real by the frontend's KPI Cards widget.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function summary(): JsonResponse
    {
        $summary = $this->dashboard->summary();

        return ApiResponse::success(
            data: new DashboardSummaryResource($summary),
        );
    }
}
