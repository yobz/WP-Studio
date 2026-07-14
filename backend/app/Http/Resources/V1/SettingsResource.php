<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'workspace' => [
                'name' => $this->resource->workspaceName,
                'slug' => $this->resource->workspaceSlug,
                'member_count' => $this->resource->memberCount,
            ],
            'user' => [
                'name' => $this->resource->userName,
                'email' => $this->resource->userEmail,
                'role' => $this->resource->userRole,
            ],
        ];
    }
}
