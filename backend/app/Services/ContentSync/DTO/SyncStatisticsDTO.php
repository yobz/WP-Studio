<?php

namespace App\Services\ContentSync\DTO;

final readonly class SyncStatisticsDTO
{
    public function __construct(
        public string $contentType,
        public int $totalSynced,
        public ?string $lastSyncedAt,
        public string $siteStatus,
        public ?string $connectionError,
    ) {}
}
