<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;

class SitePolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, Site $site): bool
    {
        return $site->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return in_array($workspace->roleFor($user), [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }

    public function update(User $user, Site $site): bool
    {
        $role = $site->workspace->roleFor($user);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }

    public function delete(User $user, Site $site): bool
    {
        $role = $site->workspace->roleFor($user);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }

    public function restore(User $user, Site $site): bool
    {
        return $site->workspace->roleFor($user) === WorkspaceRole::Owner;
    }

    public function forceDelete(User $user, Site $site): bool
    {
        return $site->workspace->roleFor($user) === WorkspaceRole::Owner;
    }
}
