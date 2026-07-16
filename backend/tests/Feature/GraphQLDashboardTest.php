<?php

use App\Models\Post;
use App\Models\Site;

const DASHBOARD_OVERVIEW_QUERY = <<<'GRAPHQL'
    query {
        dashboardOverview {
            summary {
                connectedSites
                publishedPosts
                draftPosts
                storageUsedMb
                storageLimitMb
                monthlyVisitors
                monthlyVisitorsTrend
            }
            recentActivity {
                id
                type
                title
                siteName
                timestamp
            }
            systemHealth {
                apiStatus
                wordpressConnection
                storageUsedPercent
                queueDriver
                queuePending
                queueFailed
                queueOldestPendingSeconds
                queueStatus
            }
        }
    }
    GRAPHQL;

const ANALYTICS_PREVIEW_QUERY = <<<'GRAPHQL'
    query($range: AnalyticsRange!) {
        analyticsPreview(range: $range) {
            date
            visitors
            postsPublished
        }
    }
    GRAPHQL;

function graphqlRequest(string $query, array $variables = [])
{
    return test()->postJson('/api/v1/graphql', array_filter([
        'query' => $query,
        'variables' => $variables,
    ]));
}

it('requires authentication', function () {
    $response = graphqlRequest(DASHBOARD_OVERVIEW_QUERY);

    $response->assertUnauthorized()
        ->assertJson(['error' => ['code' => 'UNAUTHENTICATED']]);
});

it('returns dashboardOverview aggregating summary, activity, and system health in one request', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create([
        'storage_used_mb' => 1000,
        'storage_limit_mb' => 10240,
    ]);
    Post::factory()->for($site)->published()->count(2)->create();
    Post::factory()->for($site)->create();
    $site->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 500,
        'posts_published' => 1,
        'storage_used_mb' => 1000,
    ]);

    $response = graphqlRequest(DASHBOARD_OVERVIEW_QUERY);

    $response->assertOk()->assertJson([
        'data' => [
            'dashboardOverview' => [
                'summary' => [
                    'connectedSites' => 1,
                    'publishedPosts' => 2,
                    'draftPosts' => 1,
                    'storageUsedMb' => 1000,
                    'storageLimitMb' => 10240,
                    'monthlyVisitors' => 500,
                ],
                'systemHealth' => [
                    'apiStatus' => 'operational',
                ],
            ],
        ],
    ]);
    // 2 published posts + 1 draft + 1 "site connected" (SiteFactory sets
    // last_connected_at by default, so the site itself is an activity item).
    expect($response->json('data.dashboardOverview.recentActivity'))->toHaveCount(4);
});

it('isolates dashboardOverview to the current workspace only', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Site::factory()->for($workspace)->create();

    $otherWorkspaceSite = Site::factory()->create();
    Post::factory()->for($otherWorkspaceSite)->published()->count(20)->create();

    $response = graphqlRequest(DASHBOARD_OVERVIEW_QUERY);

    $response->assertOk()->assertJson([
        'data' => ['dashboardOverview' => ['summary' => ['publishedPosts' => 0]]],
    ]);
});

it('returns analyticsPreview for the requested range', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    $site->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 42,
        'posts_published' => 1,
        'storage_used_mb' => 0,
    ]);

    $response = graphqlRequest(ANALYTICS_PREVIEW_QUERY, ['range' => 'SEVEN_D']);

    $response->assertOk();
    expect($response->json('data.analyticsPreview'))->toHaveCount(7);
    $today = collect($response->json('data.analyticsPreview'))->last();
    expect($today['visitors'])->toBe(42)
        ->and($today['postsPublished'])->toBe(1);
});

it('defaults analyticsPreview to a 7-day range when no argument is given', function () {
    actingAsWorkspaceMember();

    $response = graphqlRequest('query { analyticsPreview { date } }');

    $response->assertOk();
    expect($response->json('data.analyticsPreview'))->toHaveCount(7);
});

it('returns a real queued/failed job count on systemHealth, not a placeholder', function () {
    actingAsWorkspaceMember();
    config(['queue.default' => 'database']);
    Site::factory()->create();

    $response = graphqlRequest(DASHBOARD_OVERVIEW_QUERY);

    $response->assertOk()->assertJson([
        'data' => ['dashboardOverview' => ['systemHealth' => ['queueDriver' => 'database']]],
    ]);
});

it('rejects an unknown analyticsPreview range value at the schema level', function () {
    actingAsWorkspaceMember();

    $response = graphqlRequest(ANALYTICS_PREVIEW_QUERY, ['range' => 'ONE_YEAR']);

    $response->assertStatus(200);
    expect($response->json('errors'))->not->toBeNull();
    expect($response->json('data'))->toBeNull();
});
