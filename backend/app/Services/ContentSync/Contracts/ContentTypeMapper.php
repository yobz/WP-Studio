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

    public function upsert(Site $site, MappedContent $mapped): SyncOutcome;

    public function countSynced(Site $site): int;
}
