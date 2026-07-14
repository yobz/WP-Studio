<?php

namespace App\DTOs;

final readonly class DashboardSummaryData
{
    public function __construct(
        public int $connectedSites,
        public int $publishedPosts,
        public int $draftPosts,
        public int $storageUsedMb,
        public int $storageLimitMb,
        public int $monthlyVisitors,
        public ?float $monthlyVisitorsTrend,
    ) {}
}
