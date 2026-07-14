<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'connected_sites' => $this->resource->connectedSites,
            'published_posts' => $this->resource->publishedPosts,
            'draft_posts' => $this->resource->draftPosts,
            'storage_used_mb' => $this->resource->storageUsedMb,
            'storage_limit_mb' => $this->resource->storageLimitMb,
            'monthly_visitors' => $this->resource->monthlyVisitors,
            'monthly_visitors_trend' => $this->resource->monthlyVisitorsTrend,
        ];
    }
}
