<?php

namespace App\Policies;

use App\Enums\WorkspaceRole;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;

class MediaPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, Media $media): bool
    {
        return $media->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function update(User $user, Media $media): bool
    {
        return $media->workspace->hasMember($user);
    }

    public function delete(User $user, Media $media): bool
    {
        $role = $media->workspace->roleFor($user);

        return in_array($role, [WorkspaceRole::Owner, WorkspaceRole::Admin], true);
    }
}
