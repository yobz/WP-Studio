<?php

namespace App\DTOs;

final readonly class SettingsData
{
    public function __construct(
        public string $workspaceName,
        public string $workspaceSlug,
        public int $memberCount,
        public string $userName,
        public string $userEmail,
        public ?string $userRole,
    ) {}
}
