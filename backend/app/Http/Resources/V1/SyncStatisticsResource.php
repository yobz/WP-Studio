<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncStatisticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'content_type' => $this->resource->contentType,
            'total_synced' => $this->resource->totalSynced,
            'last_synced_at' => $this->resource->lastSyncedAt,
            'site_status' => $this->resource->siteStatus,
            'connection_error' => $this->resource->connectionError,
        ];
    }
}
