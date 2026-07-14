<?php

namespace App\DTOs;

final readonly class SystemHealthData
{
    public function __construct(
        public string $apiStatus,
        public string $wordpressConnection,
        public int $storageUsedPercent,
        public int $backgroundQueuePending,
        public string $backgroundQueueStatus,
    ) {}
}
