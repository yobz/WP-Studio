<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SyncResultResource;
use App\Http\Resources\V1\SyncStatisticsResource;
use App\Http\Support\ApiResponse;
use App\Models\Site;
use App\Services\ContentSync\ContentSyncService;
use App\Services\ContentSync\Mappers\WordPressPostMapper;
use Illuminate\Http\JsonResponse;

class ContentSyncController extends Controller
{
    public function __construct(
        private readonly ContentSyncService $contentSync,
        private readonly WordPressPostMapper $postMapper,
    ) {}

    public function sync(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $result = $this->contentSync->sync($site, $this->postMapper);

        return ApiResponse::success(data: new SyncResultResource($result));
    }

    public function syncStatus(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $statistics = $this->contentSync->statistics($site, $this->postMapper);

        return ApiResponse::success(data: new SyncStatisticsResource($statistics));
    }
}
