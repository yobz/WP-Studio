<?php

namespace App\Services\ContentSync\Contracts;

use App\Models\Site;
use App\Services\ContentSync\DTO\MappedContent;
use App\Services\ContentSync\Enums\SyncOutcome;

interface ContentTypeMapper
{
    public function contentType(): string;

    public function wordpressEndpoint(): string;

    public function shouldImport(array $wordpressItem): bool;

    public function map(array $wordpressItem): MappedContent;

    /**
     * Batch-loads existing records for the given WordPress IDs so upsert()
     * can look them up in memory instead of running one query per item.
     */
    public function preloadExisting(Site $site, array $wordpressIds): void;

    public function upsert(Site $site, MappedContent $mapped): SyncOutcome;

    public function countSynced(Site $site): int;
}
