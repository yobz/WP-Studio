<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SystemHealthResource;
use App\Http\Support\ApiResponse;
use App\Services\SystemHealthService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class SystemHealthController extends Controller
{
    public function __construct(
        private readonly SystemHealthService $systemHealth,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(): JsonResponse
    {
        $status = $this->systemHealth->status($this->workspaceContext->get());

        return ApiResponse::success(data: new SystemHealthResource($status));
    }
}
