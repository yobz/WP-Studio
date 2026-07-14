<?php

use App\Enums\SiteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\Site;
use App\Models\SiteCredential;
use Illuminate\Support\Facades\Http;

it('queues a sync and, once run, imports posts from a connected WordPress site, skipping trashed posts', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();

    $response = $this->postJson("/api/v1/sites/{$site->id}/sync");

    $response->assertStatus(202)->assertJson([
        'success' => true,
        'data' => ['status' => 'queued', 'site_id' => $site->id],
    ]);
    expect(Post::query()->where('site_id', $site->id)->count())->toBe(2);
    expect(Post::query()->where('wordpress_post_id', 103)->exists())->toBeFalse();
    expect($site->fresh()->status)->toBe(SiteStatus::Connected);
});

it('maps WordPress post fields onto the local schema correctly', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $published = Post::query()->where('wordpress_post_id', 101)->sole();
    expect($published->title)->toBe('Hello World')
        ->and($published->status->value)->toBe('published')
        ->and($published->wordpress_url)->toBe('https://example.com/hello-world')
        ->and($published->sync_status)->toBe('synced')
        ->and($published->sync_hash)->not->toBeNull()
        ->and($published->last_synced_at)->not->toBeNull();

    $draft = Post::query()->where('wordpress_post_id', 102)->sole();
    expect($draft->status->value)->toBe('draft');
});

it('is idempotent — re-syncing unchanged posts does not create duplicates or rewrite rows', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    expect(Post::query()->where('site_id', $site->id)->count())->toBe(2);
});

it('detects an update when WordPress content changes and updates the existing row', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();

    Http::fake([
        '*/wp-json/wp/v2/posts*' => Http::sequence()
            ->push([
                [
                    'id' => 101,
                    'title' => ['rendered' => 'Hello World'],
                    'status' => 'publish',
                    'date_gmt' => '2026-01-01T00:00:00',
                    'modified_gmt' => '2026-01-02T00:00:00',
                    'link' => 'https://example.com/hello-world',
                ],
            ], 200, ['X-WP-TotalPages' => '1'])
            ->push([
                [
                    'id' => 101,
                    'title' => ['rendered' => 'Hello World (Updated)'],
                    'status' => 'publish',
                    'date_gmt' => '2026-01-01T00:00:00',
                    'modified_gmt' => '2026-01-05T00:00:00',
                    'link' => 'https://example.com/hello-world',
                ],
            ], 200, ['X-WP-TotalPages' => '1']),
    ]);

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    expect(Post::query()->where('site_id', $site->id)->count())->toBe(1);
    expect(Post::query()->where('wordpress_post_id', 101)->sole()->title)
        ->toBe('Hello World (Updated)');
});

it('refuses to sync a site with no stored credential', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    $this->postJson("/api/v1/sites/{$site->id}/sync")
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'CONTENT_SYNC_FAILED']]);
});

it('marks the site as errored when WordPress is unreachable during sync', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollectionUnreachable();

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(503);

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Error)
        ->and($site->connection_error)->not->toBeNull();
});

it('cannot sync a site in another workspace', function () {
    actingAsWorkspaceMember();
    $otherSite = Site::factory()->create();
    SiteCredential::factory()->for($otherSite)->create();

    $this->postJson("/api/v1/sites/{$otherSite->id}/sync")->assertForbidden();
});

it('lets any workspace member trigger a sync, not just owners/admins', function () {
    [, $workspace] = actingAsWorkspaceMember(role: WorkspaceRole::Member);
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();

    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);
});

it('reports sync status reflecting the current synced-post count', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();
    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $response = $this->getJson("/api/v1/sites/{$site->id}/sync-status");

    $response->assertOk()->assertJson([
        'data' => [
            'content_type' => 'post',
            'total_synced' => 2,
            'site_status' => 'connected',
        ],
    ]);
    expect($response->json('data.last_synced_at'))->not->toBeNull();
});

it('lists synced posts through the existing posts index endpoint, scoped to the site', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressPostsCollection();
    $this->postJson("/api/v1/sites/{$site->id}/sync")->assertStatus(202);

    $response = $this->getJson("/api/v1/posts?site_id={$site->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});
