<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;

/**
 * Real authorization logic against the new Workspace/User membership
 * model — but still not wired into any route (`SiteController` has no
 * `authorize()`/`can:` middleware calls yet). Every route stays open
 * until Milestone 8 gives a request an authenticated user to check
 * `Gate::forUser()` against; wiring these in earlier would 403 every
 * request today, since `auth()->user()` is always null. Tested
 * directly against policy methods (see tests/Feature/SitePolicyTest.php)
 * so the logic is proven correct before it's load-bearing. See
 * docs/adr/0005-domain-model.md.
 */
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
