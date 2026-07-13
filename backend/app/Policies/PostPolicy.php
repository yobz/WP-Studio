<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;

/**
 * Same posture as SitePolicy — real logic, not wired to any route yet.
 * Authorization flows through the post's site's workspace rather than
 * a direct post-owner column, since there isn't one (a post doesn't
 * have its own membership list; it inherits its site's). See
 * docs/adr/0005-domain-model.md.
 */
class PostPolicy
{
    public function viewAny(User $user, Site $site): bool
    {
        return $site->workspace->hasMember($user);
    }

    public function view(User $user, Post $post): bool
    {
        return $post->site->workspace->hasMember($user);
    }

    public function create(User $user, Site $site): bool
    {
        return $site->workspace->hasMember($user);
    }

    public function update(User $user, Post $post): bool
    {
        return $post->site->workspace->hasMember($user);
    }

    public function delete(User $user, Post $post): bool
    {
        $role = $post->site->workspace->roleFor($user);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }

    public function restore(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    public function forceDelete(User $user, Post $post): bool
    {
        return $post->site->workspace->roleFor($user) === WorkspaceRole::Owner;
    }
}
