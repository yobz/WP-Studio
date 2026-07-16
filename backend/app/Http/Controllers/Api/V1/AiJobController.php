<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\GenerateContentRequest;
use App\Http\Resources\V1\AiJobResource;
use App\Http\Support\ApiResponse;
use App\Models\AiJob;
use App\Services\AI\AiJobService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class AiJobController extends Controller
{
    public function __construct(
        private readonly AiJobService $aiJobs,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function store(GenerateContentRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();
        $this->authorize('create', [AiJob::class, $workspace]);

        $job = $this->aiJobs->create($workspace, $request->user(), $request->validated('prompt'));

        return ApiResponse::success(
            data: ['status' => 'queued', 'job_id' => $job->id],
            status: 202,
        );
    }

    public function show(AiJob $aiJob): JsonResponse
    {
        $this->authorize('view', $aiJob);

        return ApiResponse::success(data: new AiJobResource($aiJob));
    }
}
