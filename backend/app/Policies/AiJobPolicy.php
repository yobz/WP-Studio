<?php

namespace App\Policies;

use App\Models\AiJob;
use App\Models\User;
use App\Models\Workspace;

class AiJobPolicy
{
    public function viewAny(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }

    public function view(User $user, AiJob $aiJob): bool
    {
        return $aiJob->workspace->hasMember($user);
    }

    public function create(User $user, Workspace $workspace): bool
    {
        return $workspace->hasMember($user);
    }
}
