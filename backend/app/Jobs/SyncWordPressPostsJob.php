<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\ContentSync\ContentSyncService;
use App\Services\ContentSync\Exceptions\ContentSyncException;
use App\Services\ContentSync\Mappers\WordPressPostMapper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncWordPressPostsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(public readonly Site $site) {}

    public function uniqueId(): string
    {
        return "content-sync-site-{$this->site->id}";
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ContentSyncService $contentSync, WordPressPostMapper $mapper): void
    {
        try {
            $contentSync->sync($this->site, $mapper);
        } catch (ContentSyncException $e) {
            $this->fail($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->site->update([
            'status' => SiteStatus::Error,
            'connection_error' => $exception?->getMessage() ?? 'The sync job failed after exhausting all retries.',
            'last_checked_at' => now(),
        ]);
    }
}
