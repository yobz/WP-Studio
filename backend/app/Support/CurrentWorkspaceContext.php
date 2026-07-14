<?php

namespace App\Support;

use App\Models\Workspace;
use RuntimeException;

class CurrentWorkspaceContext
{
    private ?Workspace $workspace = null;

    public function set(Workspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function get(): Workspace
    {
        if ($this->workspace === null) {
            throw new RuntimeException(
                'Current workspace has not been resolved for this request — '.
                'is this route missing the ResolveCurrentWorkspace middleware?',
            );
        }

        return $this->workspace;
    }
}
