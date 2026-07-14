<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexAnalyticsRequest;
use App\Http\Resources\V1\AnalyticsPointResource;
use App\Http\Support\ApiResponse;
use App\Services\AnalyticsService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(IndexAnalyticsRequest $request): JsonResponse
    {
        $range = $request->validated('range') ?? '7d';
        $points = $this->analytics->visitorsByRange($this->workspaceContext->get(), $range);

        return ApiResponse::success(data: AnalyticsPointResource::collection($points));
    }
}
