<?php

namespace App\Jobs;

use App\Models\Post;
use App\Services\Media\Exceptions\MediaDownloadException;
use App\Services\Media\MediaService;
use App\Services\WordPress\Contracts\WordPressClientContract;
use App\Services\WordPress\Exceptions\WordPressIntegrationException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DownloadMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly Post $post,
        public readonly int $wordpressMediaId,
    ) {}

    public function uniqueId(): string
    {
        return "download-media-post-{$this->post->id}";
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(WordPressClientContract $client, MediaService $media): void
    {
        $site = $this->post->site;
        $credential = $site->credential;

        if ($credential === null) {
            $this->fail(new MediaDownloadException('the site has no stored credential.'));

            return;
        }

        try {
            $remoteMedia = $client->fetchItem(
                $site->url,
                "/wp-json/wp/v2/media/{$this->wordpressMediaId}",
                $credential->wp_username,
                $credential->application_password,
            );

            $media->downloadForWordPressPost($this->post, $site, $remoteMedia);
        } catch (WordPressIntegrationException|MediaDownloadException $e) {
            $this->fail($e);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('Featured image download failed after exhausting retries.', [
            'post_id' => $this->post->id,
            'wordpress_media_id' => $this->wordpressMediaId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
