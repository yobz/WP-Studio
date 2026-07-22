<?php

namespace App\Services\ContentSync;

use App\Enums\SiteStatus;
use App\Events\ContentSynced;
use App\Models\Site;
use App\Services\ContentSync\Contracts\ContentTypeMapper;
use App\Services\ContentSync\DTO\SyncResultDTO;
use App\Services\ContentSync\DTO\SyncStatisticsDTO;
use App\Services\ContentSync\Exceptions\ContentSyncException;
use App\Services\WordPress\Contracts\WordPressClientContract;
use App\Services\WordPress\Exceptions\WordPressIntegrationException;
use App\Services\WordPress\Security\UrlSafetyValidator;
use Throwable;

class ContentSyncService
{
    private const PER_PAGE = 100;

    private const MAX_PAGES = 20;

    public function __construct(
        private readonly WordPressClientContract $client,
        private readonly UrlSafetyValidator $urlSafety,
    ) {}

    public function sync(Site $site, ContentTypeMapper $mapper): SyncResultDTO
    {
        $credential = $site->credential;
        if ($credential === null) {
            throw new ContentSyncException('this site has no stored credential — reconnect it to continue.');
        }

        $this->urlSafety->assertSafe($site->url);

        $site->update(['status' => SiteStatus::Syncing]);

        $startedAt = now();
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        try {
            $page = 1;
            $totalPages = 1;

            do {
                $collection = $this->client->fetchCollection(
                    $site->url,
                    $mapper->wordpressEndpoint(),
                    ['page' => $page, 'per_page' => self::PER_PAGE],
                    $credential->wp_username,
                    $credential->application_password,
                );

                $totalPages = min($collection->totalPages, self::MAX_PAGES);

                $mapper->preloadExisting($site, array_column($collection->items, 'id'));

                foreach ($collection->items as $item) {
                    if (! $mapper->shouldImport($item)) {
                        continue;
                    }

                    try {
                        $mapped = $mapper->map($item);
                        $outcome = $mapper->upsert($site, $mapped);

                        match ($outcome->value) {
                            'created' => $created++,
                            'updated' => $updated++,
                            'skipped' => $skipped++,
                        };
                    } catch (Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'wordpress_id' => $item['id'] ?? null,
                            'message' => $e->getMessage(),
                        ];
                    }
                }

                $page++;
            } while ($page <= $totalPages);
        } catch (WordPressIntegrationException $e) {
            $site->update([
                'status' => SiteStatus::Error,
                'connection_error' => $e->getMessage(),
                'last_checked_at' => now(),
            ]);

            throw $e;
        }

        $site->update([
            'status' => SiteStatus::Connected,
            'last_synced_at' => now(),
        ]);

        ContentSynced::dispatch($site, $mapper->contentType(), $created, $updated, $skipped, $failed);

        return new SyncResultDTO(
            contentType: $mapper->contentType(),
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failed: $failed,
            errors: $errors,
            startedAt: $startedAt->toIso8601String(),
            finishedAt: now()->toIso8601String(),
        );
    }

    public function statistics(Site $site, ContentTypeMapper $mapper): SyncStatisticsDTO
    {
        return new SyncStatisticsDTO(
            contentType: $mapper->contentType(),
            totalSynced: $mapper->countSynced($site),
            lastSyncedAt: $site->last_synced_at?->toIso8601String(),
            siteStatus: $site->status->value,
            connectionError: $site->connection_error,
        );
    }
}
