<?php

namespace App\DTOs;

final readonly class ActivityItemData
{
    public function __construct(
        public string $id,
        public string $type,
        public string $title,
        public string $siteName,
        public string $timestamp,
    ) {}
}
