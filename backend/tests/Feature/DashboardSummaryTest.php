<?php

use App\Models\Post;
use App\Models\Site;

it('returns a successful envelope with the expected shape', function () {
    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertOk()
        ->assertJsonStructure([
            'success',
            'data' => [
                'connected_sites',
                'published_posts',
                'draft_posts',
                'storage_used_mb',
                'storage_limit_mb',
                'monthly_visitors',
                'monthly_visitors_trend',
            ],
        ])
        ->assertJson(['success' => true]);
});

it('counts only connected sites and correctly aggregates their posts', function () {
    $connected = Site::factory()->create([
        'storage_used_mb' => 1000,
        'storage_limit_mb' => 10240,
    ]);
    Post::factory()->for($connected)->published()->count(3)->create();
    Post::factory()->for($connected)->count(2)->create(); // draft
    Post::factory()->for($connected)->inReview()->create();
    $connected->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 500,
        'posts_published' => 1,
        'storage_used_mb' => 1000,
    ]);

    // A disconnected site's posts and visitors must not be counted.
    $disconnected = Site::factory()->disconnected()->create([
        'storage_used_mb' => 5000,
        'storage_limit_mb' => 10240,
    ]);
    Post::factory()->for($disconnected)->published()->count(10)->create();
    $disconnected->analyticsSnapshots()->create([
        'snapshot_date' => today(),
        'visitors' => 9999,
        'posts_published' => 0,
        'storage_used_mb' => 5000,
    ]);

    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'connected_sites' => 1,
            'published_posts' => 3,
            'draft_posts' => 3, // 2 draft + 1 in-review
            'storage_used_mb' => 1000,
            'storage_limit_mb' => 10240,
            'monthly_visitors' => 500,
        ],
    ]);
});

it('returns zeroed values and a null trend when there are no sites at all', function () {
    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'connected_sites' => 0,
            'published_posts' => 0,
            'draft_posts' => 0,
            'storage_used_mb' => 0,
            'storage_limit_mb' => 0,
            'monthly_visitors' => 0,
            'monthly_visitors_trend' => null,
        ],
    ]);
});

it('computes a real period-over-period visitor trend from snapshot history', function () {
    $site = Site::factory()->create();

    // Previous 14-day window: 100/day = 1,400 total.
    foreach (range(14, 27) as $daysAgo) {
        $site->analyticsSnapshots()->create([
            'snapshot_date' => today()->subDays($daysAgo),
            'visitors' => 100,
            'posts_published' => 0,
            'storage_used_mb' => 0,
        ]);
    }

    // Current 14-day window: 200/day = 2,800 total — a 100% increase.
    foreach (range(0, 13) as $daysAgo) {
        $site->analyticsSnapshots()->create([
            'snapshot_date' => today()->subDays($daysAgo),
            'visitors' => 200,
            'posts_published' => 0,
            'storage_used_mb' => 0,
        ]);
    }

    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJson([
        'data' => [
            'monthly_visitors' => 2800,
            'monthly_visitors_trend' => 100.0,
        ],
    ]);
});

it('sends a request id header on every response', function () {
    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertHeader('X-Request-Id');
});
