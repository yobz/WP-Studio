<?php

namespace App\DTOs;

/**
 * The internal shape `DashboardService` computes and
 * `DashboardSummaryResource` renders from. Deliberately raw/numeric,
 * not pre-formatted for display ("2.4 GB / 10 GB", "+8.2%") — display
 * formatting is a frontend concern (see
 * src/features/dashboard/types/dashboard.types.ts's own doc comment
 * making the same call for the mock layer). Keeping this a plain
 * readonly DTO rather than passing arrays around means the shape is
 * checked by the type system between the service and the resource,
 * not just by convention.
 */
final readonly class DashboardSummaryData
{
    public function __construct(
        public int $connectedSites,
        public int $publishedPosts,
        public int $draftPosts,
        public int $storageUsedMb,
        public int $storageLimitMb,
        // Trailing-14-day sum across connected sites' AnalyticsSnapshot
        // rows — a practical proxy for "this month," not a true
        // calendar-month window (see DashboardService::summary()).
        public int $monthlyVisitors,
        // Percentage change vs. the prior 14-day window; null when
        // there's no prior-period data to compare against (e.g. a
        // brand-new site with under 28 days of snapshot history).
        public ?float $monthlyVisitorsTrend,
    ) {}
}
