<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspaces = $this->workspaces->map(fn ($workspace) => [
            'id' => $workspace->id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'role' => $workspace->pivot->role,
        ]);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'workspaces' => $workspaces,
            'current_workspace_id' => $this->defaultWorkspace()?->id,
        ];
    }
}
