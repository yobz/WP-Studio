<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;

class CurrentWorkspaceResolver
{
    public function resolve(User $user, Request $request): Workspace
    {
        $requestedId = $request->header('X-Workspace-Id') ?? $request->query('workspace_id');

        if ($requestedId !== null) {
            return $this->resolveRequested($user, (int) $requestedId);
        }

        return $this->resolveDefault($user);
    }

    private function resolveRequested(User $user, int $workspaceId): Workspace
    {
        $workspace = Workspace::find($workspaceId);

        if ($workspace === null || ! $workspace->hasMember($user)) {
            throw new AuthorizationException('You are not a member of this workspace.');
        }

        return $workspace;
    }

    private function resolveDefault(User $user): Workspace
    {
        $workspace = $user->defaultWorkspace();

        if ($workspace === null) {
            throw new AuthorizationException('You do not belong to a workspace yet.');
        }

        return $workspace;
    }
}
