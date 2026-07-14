<?php

namespace App\Services\ContentSync\DTO;

final readonly class SyncResultDTO
{
    public function __construct(
        public string $contentType,
        public int $created,
        public int $updated,
        public int $skipped,
        public int $failed,
        public array $errors,
        public string $startedAt,
        public string $finishedAt,
    ) {}
}
