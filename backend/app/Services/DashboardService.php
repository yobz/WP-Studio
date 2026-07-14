<?php

namespace App\Services;

use App\DTOs\DashboardSummaryData;
use App\Models\AnalyticsSnapshot;
use App\Models\Post;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class DashboardService
{
    private const TREND_WINDOW_DAYS = 14;

    public function summary(Workspace $workspace): DashboardSummaryData
    {
        $connectedSites = $workspace->sites()->connected()->get();
        $connectedSiteIds = $connectedSites->pluck('id');

        [$currentVisitors, $previousVisitors] = $this->visitorWindows($connectedSiteIds);

        return new DashboardSummaryData(
            connectedSites: $connectedSites->count(),
            publishedPosts: Post::query()
                ->whereIn('site_id', $connectedSiteIds)
                ->published()
                ->count(),
            draftPosts: Post::query()
                ->whereIn('site_id', $connectedSiteIds)
                ->unpublished()
                ->count(),
            storageUsedMb: (int) $connectedSites->sum('storage_used_mb'),
            storageLimitMb: (int) $connectedSites->sum('storage_limit_mb'),
            monthlyVisitors: $currentVisitors,
            monthlyVisitorsTrend: $this->percentChange($currentVisitors, $previousVisitors),
        );
    }

    private function visitorWindows($siteIds): array
    {
        $today = Carbon::today();
        $currentStart = $today->copy()->subDays(self::TREND_WINDOW_DAYS - 1);
        $previousEnd = $currentStart->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays(self::TREND_WINDOW_DAYS - 1);

        $current = (int) AnalyticsSnapshot::query()
            ->whereIn('site_id', $siteIds)
            ->whereBetween('snapshot_date', [$currentStart, $today])
            ->sum('visitors');

        $previous = (int) AnalyticsSnapshot::query()
            ->whereIn('site_id', $siteIds)
            ->whereBetween('snapshot_date', [$previousStart, $previousEnd])
            ->sum('visitors');

        return [$current, $previous];
    }

    private function percentChange(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
