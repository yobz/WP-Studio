<?php

namespace App\Services\WordPress\Contracts;

use App\Services\WordPress\DTO\WordPressCollectionPage;
use App\Services\WordPress\DTO\WordPressSiteInfo;

interface WordPressClientContract
{
    public function fetchSiteInfo(string $url, string $username, string $applicationPassword): WordPressSiteInfo;

    public function fetchCollection(string $url, string $endpoint, array $query, string $username, string $applicationPassword): WordPressCollectionPage;

    public function fetchItem(string $url, string $endpoint, string $username, string $applicationPassword): array;
}
