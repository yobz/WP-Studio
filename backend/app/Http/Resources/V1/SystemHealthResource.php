<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemHealthResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'api_status' => $this->resource->apiStatus,
            'wordpress_connection' => $this->resource->wordpressConnection,
            'storage_used_percent' => $this->resource->storageUsedPercent,
            'background_queue' => [
                'driver' => $this->resource->queueDriver,
                'pending' => $this->resource->queuePending,
                'failed' => $this->resource->queueFailed,
                'oldest_pending_seconds' => $this->resource->queueOldestPendingSeconds,
                'status' => $this->resource->queueStatus,
            ],
        ];
    }
}
