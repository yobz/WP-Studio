<?php

namespace App\GraphQL\Queries;

use App\Services\AnalyticsService;
use App\Support\CurrentWorkspaceContext;

class AnalyticsPreview
{
    public function __construct(
        private readonly AnalyticsService $analytics,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    /**
     * @param  array{range: string}  $args
     */
    public function __invoke(mixed $root, array $args): array
    {
        return $this->analytics->visitorsByRange($this->workspaceContext->get(), $args['range']);
    }
}
