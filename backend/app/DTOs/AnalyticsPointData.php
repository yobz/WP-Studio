<?php

namespace App\DTOs;

final readonly class AnalyticsPointData
{
    public function __construct(
        public string $date,
        public int $visitors,
        public int $postsPublished,
    ) {}
}
