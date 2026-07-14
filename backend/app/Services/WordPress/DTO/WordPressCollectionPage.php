<?php

namespace App\Services\WordPress\DTO;

final readonly class WordPressCollectionPage
{
    public function __construct(
        public array $items,
        public int $totalPages,
    ) {}
}
