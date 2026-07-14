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
                'pending' => $this->resource->backgroundQueuePending,
                'status' => $this->resource->backgroundQueueStatus,
            ],
        ];
    }
}
