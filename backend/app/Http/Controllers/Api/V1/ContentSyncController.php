<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\SyncStatisticsResource;
use App\Http\Support\ApiResponse;
use App\Jobs\SyncWordPressPostsJob;
use App\Models\Site;
use App\Services\ContentSync\ContentSyncService;
use App\Services\ContentSync\Exceptions\ContentSyncException;
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

        if ($site->credential === null) {
            throw new ContentSyncException('this site has no stored credential — reconnect it to continue.');
        }

        $site->update(['status' => SiteStatus::Syncing]);

        SyncWordPressPostsJob::dispatch($site);

        return ApiResponse::success(
            data: ['status' => 'queued', 'site_id' => $site->id],
            status: 202,
        );
    }

    public function syncStatus(Site $site): JsonResponse
    {
        $this->authorize('view', $site);

        $statistics = $this->contentSync->statistics($site, $this->postMapper);

        return ApiResponse::success(data: new SyncStatisticsResource($statistics));
    }
}
