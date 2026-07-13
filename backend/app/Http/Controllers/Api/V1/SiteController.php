<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexSitesRequest;
use App\Http\Requests\V1\StoreSiteRequest;
use App\Http\Requests\V1\UpdateSiteRequest;
use App\Http\Resources\V1\SiteResource;
use App\Http\Support\ApiResponse;
use App\Models\Site;
use App\Models\Workspace;
use App\Services\SiteService;
use Illuminate\Http\JsonResponse;

/**
 * Real CRUD over `Site` records (Milestone 7). What's still a future
 * milestone's job: the actual WordPress OAuth/API-key *connection*
 * flow that would create these rows from a real site handshake
 * (Milestone 9) — this controller manages the data once it exists,
 * it doesn't establish the connection. No `authorize()` calls yet —
 * see SitePolicy's own doc comment for why. See
 * docs/adr/0005-domain-model.md.
 */
class SiteController extends Controller
{
    public function __construct(private readonly SiteService $sites) {}

    public function index(IndexSitesRequest $request): JsonResponse
    {
        $query = Site::query();

        if ($workspaceId = $request->validated('workspace_id')) {
            $query->where('workspace_id', $workspaceId);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success(
            data: SiteResource::collection($query->latest()->get()),
        );
    }

    public function show(Site $site): JsonResponse
    {
        return ApiResponse::success(data: new SiteResource($site));
    }

    public function store(StoreSiteRequest $request): JsonResponse
    {
        $workspace = Workspace::findOrFail($request->validated('workspace_id'));
        $site = $this->sites->create($workspace, $request->safe()->except('workspace_id'));

        return ApiResponse::success(data: new SiteResource($site), status: 201);
    }

    public function update(UpdateSiteRequest $request, Site $site): JsonResponse
    {
        $site = $this->sites->update($site, $request->validated());

        return ApiResponse::success(data: new SiteResource($site));
    }

    public function destroy(Site $site): JsonResponse
    {
        $this->sites->delete($site);

        return ApiResponse::success(data: null, status: 200);
    }
}
