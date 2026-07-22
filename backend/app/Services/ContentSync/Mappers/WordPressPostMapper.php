<?php

namespace App\Services\ContentSync\Mappers;

use App\Enums\PostStatus;
use App\Jobs\DownloadMediaJob;
use App\Models\Post;
use App\Models\Site;
use App\Services\ContentSync\Contracts\ContentTypeMapper;
use App\Services\ContentSync\DTO\MappedContent;
use App\Services\ContentSync\Enums\SyncOutcome;
use Carbon\CarbonImmutable;
use Throwable;

class WordPressPostMapper implements ContentTypeMapper
{
    /** @var array<int, Post> keyed by wordpress_post_id, scoped to the current page */
    private array $existingByWordpressId = [];

    public function contentType(): string
    {
        return 'post';
    }

    public function wordpressEndpoint(): string
    {
        return '/wp-json/wp/v2/posts';
    }

    public function shouldImport(array $wordpressItem): bool
    {
        return ($wordpressItem['status'] ?? null) !== 'trash';
    }

    public function map(array $wordpressItem): MappedContent
    {
        $wordpressId = (int) ($wordpressItem['id'] ?? 0);

        $attributes = [
            'title' => $this->extractTitle($wordpressItem),
            'status' => $this->mapStatus((string) ($wordpressItem['status'] ?? 'draft')),
            'published_at' => $this->parseDate($wordpressItem['date_gmt'] ?? null),
            'wordpress_post_id' => $wordpressId,
            'wordpress_modified_at' => $this->parseDate($wordpressItem['modified_gmt'] ?? null),
            'wordpress_url' => is_string($wordpressItem['link'] ?? null) ? $wordpressItem['link'] : null,
        ];

        $featuredMediaId = (int) ($wordpressItem['featured_media'] ?? 0);

        return new MappedContent(
            wordpressId: $wordpressId,
            attributes: $attributes,
            hash: hash('sha256', json_encode([...$attributes, 'featured_media_id' => $featuredMediaId])),
            featuredMediaId: $featuredMediaId > 0 ? $featuredMediaId : null,
        );
    }

    public function preloadExisting(Site $site, array $wordpressIds): void
    {
        $this->existingByWordpressId = Post::query()
            ->where('site_id', $site->id)
            ->whereIn('wordpress_post_id', $wordpressIds)
            ->get()
            ->keyBy('wordpress_post_id')
            ->all();
    }

    public function upsert(Site $site, MappedContent $mapped): SyncOutcome
    {
        $existing = $this->existingByWordpressId[$mapped->wordpressId] ?? null;

        if ($existing === null) {
            $post = $site->posts()->create([
                ...$mapped->attributes,
                'sync_status' => 'synced',
                'sync_hash' => $mapped->hash,
                'last_synced_at' => now(),
            ]);

            $this->syncFeaturedImage($post, $mapped->featuredMediaId);

            return SyncOutcome::Created;
        }

        if ($existing->sync_hash === $mapped->hash) {
            return SyncOutcome::Skipped;
        }

        $existing->update([
            ...$mapped->attributes,
            'sync_status' => 'synced',
            'sync_hash' => $mapped->hash,
            'last_synced_at' => now(),
        ]);

        $this->syncFeaturedImage($existing, $mapped->featuredMediaId);

        return SyncOutcome::Updated;
    }

    /**
     * Downloads a post's WordPress featured image exactly once per unique
     * WordPress media ID — a no-op when it's already been downloaded for
     * this post, and a synchronous, IO-free removal when WordPress reports
     * the post no longer has one.
     */
    private function syncFeaturedImage(Post $post, ?int $featuredMediaId): void
    {
        $current = $post->featuredImage()->first();

        if ($featuredMediaId === null) {
            $current?->delete();

            return;
        }

        if ($current !== null && $current->source_id === (string) $featuredMediaId) {
            return;
        }

        $current?->delete();

        DownloadMediaJob::dispatch($post, $featuredMediaId);
    }

    public function countSynced(Site $site): int
    {
        return $site->posts()->syncedFromWordPress()->count();
    }

    private function extractTitle(array $wordpressItem): string
    {
        $title = $wordpressItem['title'] ?? '';

        if (is_array($title)) {
            return (string) ($title['rendered'] ?? '');
        }

        return (string) $title;
    }

    private function mapStatus(string $wordpressStatus): string
    {
        return match ($wordpressStatus) {
            'publish' => PostStatus::Published->value,
            'pending' => PostStatus::InReview->value,
            default => PostStatus::Draft->value,
        };
    }

    private function parseDate(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }
}
