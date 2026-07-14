<?php

namespace App\Services\ContentSync\Mappers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\Site;
use App\Services\ContentSync\Contracts\ContentTypeMapper;
use App\Services\ContentSync\DTO\MappedContent;
use App\Services\ContentSync\Enums\SyncOutcome;
use Carbon\CarbonImmutable;
use Throwable;

class WordPressPostMapper implements ContentTypeMapper
{
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

        return new MappedContent(
            wordpressId: $wordpressId,
            attributes: $attributes,
            hash: hash('sha256', json_encode($attributes)),
        );
    }

    public function upsert(Site $site, MappedContent $mapped): SyncOutcome
    {
        $existing = Post::query()
            ->where('site_id', $site->id)
            ->where('wordpress_post_id', $mapped->wordpressId)
            ->first();

        if ($existing === null) {
            $site->posts()->create([
                ...$mapped->attributes,
                'sync_status' => 'synced',
                'sync_hash' => $mapped->hash,
                'last_synced_at' => now(),
            ]);

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

        return SyncOutcome::Updated;
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
