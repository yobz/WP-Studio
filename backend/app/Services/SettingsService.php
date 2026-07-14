<?php

namespace App\Services;

use App\DTOs\SettingsData;
use App\Models\User;
use App\Models\Workspace;

class SettingsService
{
    public function forUser(User $user, Workspace $workspace): SettingsData
    {
        return new SettingsData(
            workspaceName: $workspace->name,
            workspaceSlug: $workspace->slug,
            memberCount: $workspace->users()->count(),
            userName: $user->name,
            userEmail: $user->email,
            userRole: $workspace->roleFor($user)?->value,
        );
    }
}
