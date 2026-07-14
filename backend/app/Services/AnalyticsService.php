<?php

namespace App\Services;

use App\DTOs\AnalyticsPointData;
use App\Models\AnalyticsSnapshot;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

class AnalyticsService
{
    public function visitorsByRange(Workspace $workspace, string $range): array
    {
        $days = match ($range) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $siteIds = $workspace->sites()->pluck('id');
        $today = Carbon::today();
        $start = $today->copy()->subDays($days - 1);

        $snapshotsByDate = AnalyticsSnapshot::query()
            ->whereIn('site_id', $siteIds)
            ->whereBetween('snapshot_date', [$start, $today])
            ->get()
            ->groupBy(fn (AnalyticsSnapshot $snapshot) => $snapshot->snapshot_date->toDateString());

        $points = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $dayRows = $snapshotsByDate->get($date, collect());

            $points[] = new AnalyticsPointData(
                date: $date,
                visitors: (int) $dayRows->sum('visitors'),
                postsPublished: (int) $dayRows->sum('posts_published'),
            );
        }

        return $points;
    }
}
