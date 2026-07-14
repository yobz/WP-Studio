<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\DashboardSummaryResource;
use App\Http\Support\ApiResponse;
use App\Services\DashboardService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function summary(): JsonResponse
    {
        $summary = $this->dashboard->summary($this->workspaceContext->get());

        return ApiResponse::success(
            data: new DashboardSummaryResource($summary),
        );
    }
}
