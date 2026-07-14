<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'content_type' => $this->resource->contentType,
            'created' => $this->resource->created,
            'updated' => $this->resource->updated,
            'skipped' => $this->resource->skipped,
            'failed' => $this->resource->failed,
            'errors' => $this->resource->errors,
            'started_at' => $this->resource->startedAt,
            'finished_at' => $this->resource->finishedAt,
        ];
    }
}
