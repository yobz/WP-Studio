<?php

namespace App\Events;

use App\Models\Site;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentSynced
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Site $site,
        public readonly string $contentType,
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
        public readonly int $failed,
    ) {}
}
