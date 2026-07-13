<?php

namespace App\Services;

use App\DTOs\DashboardSummaryData;
use App\Models\AnalyticsSnapshot;
use App\Models\Post;
use App\Models\Site;
use Illuminate\Support\Carbon;

/**
 * No repository layer in front of `Site`/`Post`/`AnalyticsSnapshot`
 * here, deliberately — see docs/adr/0004-backend-foundation.md's
 * "Repositories" section. These are single, simple aggregate queries
 * against Eloquent models with no swappable-data-source or
 * complex-query-composition need yet; a repository would be
 * indirection with no current benefit.
 */
class DashboardService
{
    private const TREND_WINDOW_DAYS = 14;

    public function summary(): DashboardSummaryData
    {
        $connectedSites = Site::query()->connected()->get();
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

    /**
     * @param  \Illuminate\Support\Collection<int, int>  $siteIds
     * @return array{0: int, 1: int} [currentWindowVisitors, previousWindowVisitors]
     */
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
            // Not "0% change" — there's nothing to compare against,
            // which is a different, honest answer than "no change."
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
