<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SettingsResource;
use App\Http\Support\ApiResponse;
use App\Services\SettingsService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->settings->forUser($request->user(), $this->workspaceContext->get());

        return ApiResponse::success(data: new SettingsResource($data));
    }
}
