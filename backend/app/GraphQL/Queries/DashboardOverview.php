<?php

namespace App\GraphQL\Queries;

use App\Services\DashboardService;
use App\Services\SystemHealthService;
use App\Support\CurrentWorkspaceContext;

class DashboardOverview
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly SystemHealthService $systemHealth,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function __invoke(): array
    {
        $workspace = $this->workspaceContext->get();

        return [
            'summary' => $this->dashboard->summary($workspace),
            'recentActivity' => $this->dashboard->recentActivity($workspace),
            'systemHealth' => $this->systemHealth->status($workspace),
        ];
    }
}
