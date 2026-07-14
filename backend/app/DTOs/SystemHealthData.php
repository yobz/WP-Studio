<?php

namespace App\DTOs;

final readonly class SystemHealthData
{
    public function __construct(
        public string $apiStatus,
        public string $wordpressConnection,
        public int $storageUsedPercent,
        public string $queueDriver,
        public ?int $queuePending,
        public int $queueFailed,
        public ?int $queueOldestPendingSeconds,
        public string $queueStatus,
    ) {}
}
