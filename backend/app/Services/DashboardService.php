<?php

namespace App\Services;

use App\DTOs\ActivityItemData;
use App\DTOs\DashboardSummaryData;
use App\Enums\PostStatus;
use App\Models\AnalyticsSnapshot;
use App\Models\Post;
use App\Models\Site;
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

    public function recentActivity(Workspace $workspace, int $limit = 10): array
    {
        $siteIds = $workspace->sites()->pluck('id');

        $publishedPosts = Post::query()
            ->whereIn('site_id', $siteIds)
            ->published()
            ->whereNotNull('published_at')
            ->with('site:id,name')
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Post $post) => new ActivityItemData(
                id: "post-published-{$post->id}",
                type: 'post-published',
                title: $post->title,
                siteName: $post->site->name,
                timestamp: $post->published_at->toIso8601String(),
            ));

        $draftPosts = Post::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', PostStatus::Draft)
            ->with('site:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Post $post) => new ActivityItemData(
                id: "draft-created-{$post->id}",
                type: 'draft-created',
                title: $post->title,
                siteName: $post->site->name,
                timestamp: $post->created_at->toIso8601String(),
            ));

        $connectedSites = $workspace->sites()
            ->whereNotNull('last_connected_at')
            ->latest('last_connected_at')
            ->limit($limit)
            ->get()
            ->map(fn (Site $site) => new ActivityItemData(
                id: "site-connected-{$site->id}",
                type: 'site-connected',
                title: "{$site->name} connected",
                siteName: $site->name,
                timestamp: $site->last_connected_at->toIso8601String(),
            ));

        return $publishedPosts
            ->concat($draftPosts)
            ->concat($connectedSites)
            ->sortByDesc('timestamp')
            ->values()
            ->take($limit)
            ->all();
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
