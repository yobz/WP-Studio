<?php

namespace App\Services\WordPress\DTO;

final readonly class WordPressSiteInfo
{
    public function __construct(
        public string $siteName,
        public ?string $wordpressVersion,
        public ?string $phpVersion,
        public ?string $activeTheme,
        public ?int $pluginCount,
        public ?int $userCount,
        public ?string $timezone,
        public ?string $language,
    ) {}
}
