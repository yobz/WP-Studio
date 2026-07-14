<?php

use App\Jobs\DownloadMediaJob;
use App\Models\Media;
use App\Models\Post;
use App\Models\Site;
use App\Models\SiteCredential;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

function wpPostPayload(string $title, ?int $featuredMediaId = null): array
{
    $payload = [
        'id' => 101,
        'title' => ['rendered' => $title],
        'status' => 'publish',
        'date_gmt' => '2026-01-01T00:00:00',
        'modified_gmt' => '2026-01-02T00:00:00',
        'link' => 'https://example.com/hello-world',
    ];

    if ($featuredMediaId !== null) {
        $payload['featured_media'] = $featuredMediaId;
    }

    return $payload;
}

function fakeWordPressMediaItem(int $id): void
{
    Http::fake([
        "*/wp-json/wp/v2/media/{$id}" => Http::response([
            'id' => $id,
            'source_url' => "https://example.com/wp-content/uploads/cover-{$id}.jpg",
            'mime_type' => 'image/jpeg',
            'alt_text' => 'A cover photo',
            'media_details' => ['width' => 640, 'height' => 480],
        ]),
        "https://example.com/wp-content/uploads/cover-{$id}.jpg" => Http::response('fake-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

it('downloads and attaches a WordPress post\'s featured image during sync', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    Http::fake(['*/wp-json/wp/v2/posts*' => Http::response([wpPostPayload('Hello World', 55)], 200, ['X-WP-TotalPages' => '1'])]);
    fakeWordPressMediaItem(55);

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $post = Post::query()->where('wordpress_post_id', 101)->sole();
    $media = $post->featuredImage()->sole();

    expect($media->source)->toBe('wordpress')
        ->and($media->source_id)->toBe('55')
        ->and($media->workspace_id)->toBe($workspace->id)
        ->and($media->site_id)->toBe($site->id)
        ->and($media->width)->toBe(640)
        ->and($media->height)->toBe(480)
        ->and($media->alt_text)->toBe('A cover photo')
        ->and($media->mime_type)->toBe('image/jpeg');
    Storage::disk('public')->assertExists($media->storage_path);
});

it('does not re-download the featured image when a re-synced post still references the same one', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    Http::fake([
        '*/wp-json/wp/v2/posts*' => Http::sequence()
            ->push([wpPostPayload('Hello World', 55)], 200, ['X-WP-TotalPages' => '1'])
            ->push([wpPostPayload('Hello World (Updated)', 55)], 200, ['X-WP-TotalPages' => '1']),
    ]);
    fakeWordPressMediaItem(55);

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $post = Post::query()->where('wordpress_post_id', 101)->sole();
    expect($post->title)->toBe('Hello World (Updated)');
    expect(Media::query()->count())->toBe(1);
});

it('replaces the featured image when WordPress reports a different one on re-sync', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    Http::fake([
        '*/wp-json/wp/v2/posts*' => Http::sequence()
            ->push([wpPostPayload('Hello World', 55)], 200, ['X-WP-TotalPages' => '1'])
            ->push([wpPostPayload('Hello World', 56)], 200, ['X-WP-TotalPages' => '1']),
    ]);
    fakeWordPressMediaItem(55);
    fakeWordPressMediaItem(56);

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
    $firstMediaId = Post::query()->where('wordpress_post_id', 101)->sole()->featuredImage()->sole()->id;

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $post = Post::query()->where('wordpress_post_id', 101)->sole();
    $current = $post->featuredImage()->sole();

    expect($current->id)->not->toBe($firstMediaId)
        ->and($current->source_id)->toBe('56')
        ->and(Media::withTrashed()->find($firstMediaId)->trashed())->toBeTrue();
});

it('removes the local featured image when WordPress reports the post no longer has one', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    Http::fake([
        '*/wp-json/wp/v2/posts*' => Http::sequence()
            ->push([wpPostPayload('Hello World', 55)], 200, ['X-WP-TotalPages' => '1'])
            ->push([wpPostPayload('Hello World (No Image)')], 200, ['X-WP-TotalPages' => '1']),
    ]);
    fakeWordPressMediaItem(55);

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
    $post = Post::query()->where('wordpress_post_id', 101)->sole();
    expect($post->featuredImage()->exists())->toBeTrue();

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    expect($post->fresh()->featuredImage()->exists())->toBeFalse();
});

it('does not dispatch a second download job for the same post while one is already queued', function () {
    config(['queue.default' => 'database']);
    $post = Post::factory()->for(Site::factory())->create();

    DownloadMediaJob::dispatch($post, 55);
    DownloadMediaJob::dispatch($post, 55);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('configures DownloadMediaJob with real retry, backoff, and uniqueness settings', function () {
    $post = Post::factory()->make(['id' => 7]);
    $job = new DownloadMediaJob($post, 55);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30, 60])
        ->and($job->uniqueId())->toBe('download-media-post-7');
});

it('logs an observable failure instead of silently swallowing it when the download job fails permanently', function () {
    Log::spy();
    $post = Post::factory()->for(Site::factory())->create();

    (new DownloadMediaJob($post, 55))->failed(new Exception('Simulated permanent failure.'));

    Log::shouldHaveReceived('warning')->once();
});
