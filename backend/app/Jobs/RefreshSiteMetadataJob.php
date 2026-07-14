<?php

namespace App\Jobs;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Services\WordPress\SiteConnectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshSiteMetadataJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(public readonly Site $site) {}

    public function uniqueId(): string
    {
        return "refresh-metadata-site-{$this->site->id}";
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(SiteConnectionService $connections): void
    {
        $connections->refreshMetadata($this->site);
    }

    public function failed(?Throwable $exception): void
    {
        $this->site->update([
            'status' => SiteStatus::Error,
            'connection_error' => $exception?->getMessage() ?? 'The metadata refresh job failed after exhausting all retries.',
            'last_checked_at' => now(),
        ]);
    }
}
