<?php

namespace App\Services\AI;

use App\Jobs\GenerateAiContentJob;
use App\Models\AiJob;
use App\Models\User;
use App\Models\Workspace;

class AiJobService
{
    public function create(Workspace $workspace, User $user, string $prompt): AiJob
    {
        $job = AiJob::query()->create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'prompt' => $prompt,
        ]);

        GenerateAiContentJob::dispatch($job);

        return $job;
    }
}
