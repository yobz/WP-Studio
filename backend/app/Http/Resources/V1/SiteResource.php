<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SiteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'url' => $this->url,
            'status' => $this->status->value,
            'wordpress_version' => $this->wordpress_version,
            'theme' => $this->theme,
            'php_version' => $this->php_version,
            'plugin_updates_available' => $this->plugin_updates_available,
            'plugin_count' => $this->plugin_count,
            'user_count' => $this->user_count,
            'timezone' => $this->timezone,
            'language' => $this->language,
            'storage_used_mb' => $this->storage_used_mb,
            'storage_limit_mb' => $this->storage_limit_mb,
            'last_connected_at' => $this->last_connected_at?->toIso8601String(),
            'last_checked_at' => $this->last_checked_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'connection_error' => $this->connection_error,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
