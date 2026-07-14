<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexSitesRequest;
use App\Http\Requests\V1\StoreSiteRequest;
use App\Http\Requests\V1\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Http\Support\ApiResponse;
use App\Models\Site;
use App\Services\SiteService;
use App\Services\WordPress\SiteConnectionService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class SiteController extends Controller
{
    public function __construct(
        private readonly SiteService $sites,
        private readonly SiteConnectionService $connections,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(IndexSitesRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();

        $query = Site::query()->where('workspace_id', $workspace->id);

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success(
            data: SiteResource::collection($query->latest()->get()),
        );
    }

    public function show(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        return ApiResponse::success(data: new SiteResource($site));
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();
        $this->authorize('create', [Site::class, $workspace]);

        $site = $this->connections->connect($workspace, $request->validated());

        return ApiResponse::success(data: new SiteResource($site), status: 201);
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $site = $this->sites->update($site, $request->validated());

        return ApiResponse::success(data: new SiteResource($site));
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->authorize('delete', $site);

        $this->sites->delete($site);

        return ApiResponse::success(data: null, status: 200);
    }

    public function disconnect(Site $site): JsonResponse
    {
        $this->authorize('update', $site);

        $site = $this->connections->disconnect($site);

        return ApiResponse::success(data: new SiteResource($site));
    }

    public function verifyConnection(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $site = $this->connections->verifyConnection($site);

        return ApiResponse::success(data: new SiteResource($site));
    }

    public function refreshMetadata(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $site = $this->connections->refreshMetadata($site);

        return ApiResponse::success(data: new SiteResource($site));
    }
}
