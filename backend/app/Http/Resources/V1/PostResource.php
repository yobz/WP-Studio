<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'site_name' => $this->site->name,
            'title' => $this->title,
            'status' => $this->status->value,
            'published_at' => $this->published_at?->toIso8601String(),
            'wordpress_post_id' => $this->wordpress_post_id,
            'wordpress_modified_at' => $this->wordpress_modified_at?->toIso8601String(),
            'wordpress_url' => $this->wordpress_url,
            'sync_status' => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'featured_image' => $this->whenLoaded(
                'featuredImage',
                fn () => $this->featuredImage ? new MediaResource($this->featuredImage) : null,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
