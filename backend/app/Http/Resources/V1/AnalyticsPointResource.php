<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsPointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            'visitors' => $this->resource->visitors,
            'posts_published' => $this->resource->postsPublished,
        ];
    }
}
