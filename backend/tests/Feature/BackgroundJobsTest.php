<?php

use App\Enums\SiteStatus;
use App\Jobs\RefreshSiteMetadataJob;
use App\Jobs\SyncWordPressPostsJob;
use App\Models\Site;
use App\Models\SiteCredential;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('dispatches SyncWordPressPostsJob and marks the site as syncing immediately, without waiting for completion', function () {
    Queue::fake();
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();

    $response = $this->postJson("/api/v1/sites/{$site->id}/sync");

    $response->assertStatus(202)->assertJson([
        'data' => ['status' => 'queued', 'site_id' => $site->id],
    ]);
    Queue::assertPushed(SyncWordPressPostsJob::class, fn ($job) => $job->site->is($site));
    expect($site->fresh()->status)->toBe(SiteStatus::Syncing);
});

it('does not dispatch a second sync job for a site while one is already queued', function () {
    config(['queue.default' => 'database']);
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    SyncWordPressPostsJob::dispatch($site);
    SyncWordPressPostsJob::dispatch($site);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('marks the site as errored via the failed() handler if the job is reported as failed', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    (new SyncWordPressPostsJob($site))->failed(new Exception('Simulated permanent failure.'));

    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Error)
        ->and($site->connection_error)->toBe('Simulated permanent failure.');
});

it('configures SyncWordPressPostsJob with real retry, backoff, and uniqueness settings', function () {
    $site = Site::factory()->make(['id' => 1]);
    $job = new SyncWordPressPostsJob($site);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30, 60])
        ->and($job->uniqueId())->toBe('content-sync-site-1')
        ->and($job->uniqueFor)->toBeGreaterThan(0);
});

it('configures RefreshSiteMetadataJob with real retry, backoff, and uniqueness settings', function () {
    $site = Site::factory()->make(['id' => 1]);
    $job = new RefreshSiteMetadataJob($site);

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([10, 30, 60])
        ->and($job->uniqueId())->toBe('refresh-metadata-site-1');
});

it('reports real pending queue jobs when using the database driver', function () {
    config(['queue.default' => 'database']);
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    SyncWordPressPostsJob::dispatch($site);

    $response = $this->getJson('/api/v1/system-health');

    $response->assertOk()->assertJson([
        'data' => [
            'background_queue' => [
                'driver' => 'database',
                'pending' => 1,
                'failed' => 0,
                'status' => 'operational',
            ],
        ],
    ]);
});

it('reports failed queue jobs and a degraded queue status', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Some failure',
        'failed_at' => now(),
    ]);
    actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/system-health');

    $response->assertOk()->assertJson([
        'data' => ['background_queue' => ['failed' => 1, 'status' => 'degraded']],
    ]);
});

it('registers a daily scheduled task to refresh connected site metadata', function () {
    $schedule = app(Schedule::class);
    $events = collect($schedule->events())
        ->filter(fn ($event) => $event->description === 'refresh-connected-site-metadata');

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
