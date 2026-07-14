<?php

namespace App\Services;

use App\DTOs\SystemHealthData;
use App\Enums\SiteStatus;
use App\Models\Workspace;
use App\Support\DatabaseHealthChecker;
use Illuminate\Support\Collection;

class SystemHealthService
{
    public function __construct(
        private readonly DatabaseHealthChecker $databaseHealthChecker,
    ) {}

    public function status(Workspace $workspace): SystemHealthData
    {
        $database = $this->databaseHealthChecker->check();
        $sites = $workspace->sites;

        return new SystemHealthData(
            apiStatus: $database['status'] === 'ok' ? 'operational' : 'down',
            wordpressConnection: $this->wordpressConnectionStatus($sites),
            storageUsedPercent: $this->storageUsedPercent($sites),
            backgroundQueuePending: 0,
            backgroundQueueStatus: 'operational',
        );
    }

    private function wordpressConnectionStatus(Collection $sites): string
    {
        if ($sites->contains(fn ($site) => $site->status === SiteStatus::Error)) {
            return SiteStatus::Error->value;
        }

        if ($sites->contains(fn ($site) => $site->status === SiteStatus::Connected)) {
            return SiteStatus::Connected->value;
        }

        return SiteStatus::Disconnected->value;
    }

    private function storageUsedPercent(Collection $sites): int
    {
        $used = (int) $sites->sum('storage_used_mb');
        $limit = (int) $sites->sum('storage_limit_mb');

        return $limit > 0 ? (int) round(($used / $limit) * 100) : 0;
    }
}
