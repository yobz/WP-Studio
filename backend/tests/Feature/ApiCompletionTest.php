<?php

use App\Enums\SiteStatus;
use App\Models\Post;
use App\Models\Site;

// Analytics

it('requires authentication for analytics', function () {
    $this->getJson('/api/v1/analytics')->assertUnauthorized();
});

it('returns real visitor analytics aggregated from AnalyticsSnapshot, scoped to the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    $otherSite = Site::factory()->create();

    $site->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 100,
        'posts_published' => 1,
        'storage_used_mb' => 0,
    ]);
    $otherSite->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 99999,
        'posts_published' => 0,
        'storage_used_mb' => 0,
    ]);

    $response = $this->getJson('/api/v1/analytics?range=7d');

    $response->assertOk()->assertJsonStructure([
        'success',
        'data' => [['date', 'visitors', 'posts_published']],
    ]);
    expect($response->json('data'))->toHaveCount(7);
    $today = $response->json('data.6');
    expect($today['date'])->toBe(today()->toDateString())
        ->and($today['visitors'])->toBe(100)
        ->and($today['posts_published'])->toBe(1);
});

it('defaults to a 7 day analytics range and rejects an invalid range', function () {
    actingAsWorkspaceMember();

    $this->getJson('/api/v1/analytics')->assertOk()
        ->assertJson(fn ($json) => $json->has('data', 7)->etc());

    $this->getJson('/api/v1/analytics?range=1y')->assertStatus(422);
});

// Settings

it('requires authentication for settings', function () {
    $this->getJson('/api/v1/settings')->assertUnauthorized();
});

it('returns real workspace and user information', function () {
    [$user, $workspace] = actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/settings');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'workspace' => [
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'member_count' => 1,
            ],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'owner',
            ],
        ],
    ]);
});

// System Health

it('requires authentication for system health', function () {
    $this->getJson('/api/v1/system-health')->assertUnauthorized();
});

it('reports real system health derived from the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Site::factory()->for($workspace)->create([
        'status' => SiteStatus::Connected,
        'storage_used_mb' => 500,
        'storage_limit_mb' => 1000,
    ]);

    $response = $this->getJson('/api/v1/system-health');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'api_status' => 'operational',
            'wordpress_connection' => 'connected',
            'storage_used_percent' => 50,
            'background_queue' => ['pending' => 0, 'status' => 'operational'],
        ],
    ]);
});

it('reports an error wordpress_connection status when any site in the workspace has errored', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Site::factory()->for($workspace)->create(['status' => SiteStatus::Connected]);
    Site::factory()->for($workspace)->withError()->create();

    $response = $this->getJson('/api/v1/system-health');

    $response->assertOk()->assertJson(['data' => ['wordpress_connection' => 'error']]);
});

// Recent Activity

it('requires authentication for dashboard activity', function () {
    $this->getJson('/api/v1/dashboard/activity')->assertUnauthorized();
});

it('derives recent activity from real posts and site connections, scoped to the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create(['last_connected_at' => now()->subHour()]);
    Post::factory()->for($site)->published()->create(['published_at' => now()->subMinutes(5)]);
    Post::factory()->for($site)->create(['status' => 'draft']);

    $otherSite = Site::factory()->create();
    Post::factory()->for($otherSite)->published()->create();

    $response = $this->getJson('/api/v1/dashboard/activity');

    $response->assertOk()->assertJsonStructure([
        'data' => [['id', 'type', 'title', 'site_name', 'timestamp']],
    ]);
    $types = collect($response->json('data'))->pluck('type');
    expect($types)->toContain('post-published')
        ->and($types)->toContain('draft-created')
        ->and($types)->toContain('site-connected')
        ->and($response->json('data'))->toHaveCount(3);
});

// Recent Drafts (unpublished status filter)

it('filters posts to unpublished (draft + in-review) via a single status value', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    Post::factory()->for($site)->create(['status' => 'draft']);
    Post::factory()->for($site)->inReview()->create();
    Post::factory()->for($site)->published()->create();

    $response = $this->getJson('/api/v1/posts?status=unpublished');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('includes the owning site name on every post', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create(['name' => 'Acme Blog']);
    Post::factory()->for($site)->create();

    $response = $this->getJson('/api/v1/posts');

    $response->assertOk()->assertJson([
        'data' => [['site_name' => 'Acme Blog']],
    ]);
});
