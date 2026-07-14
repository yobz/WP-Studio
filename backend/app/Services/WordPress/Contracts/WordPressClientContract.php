<?php

namespace App\Services\WordPress\Contracts;

use App\Services\WordPress\DTO\WordPressSiteInfo;

interface WordPressClientContract
{
    public function fetchSiteInfo(string $url, string $username, string $applicationPassword): WordPressSiteInfo;
}
