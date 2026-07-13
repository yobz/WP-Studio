<?php

use App\Enums\WorkspaceRole;
use App\Models\AnalyticsSnapshot;
use App\Models\Post;
use App\Models\PublishingJob;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;

it('relates a workspace to its sites', function () {
    $workspace = Workspace::factory()->create();
    $site = Site::factory()->for($workspace)->create();

    expect($workspace->sites)->toHaveCount(1)
        ->and($workspace->sites->first()->is($site))->toBeTrue()
        ->and($site->workspace->is($workspace))->toBeTrue();
});

it('attaches users to a workspace with a role via the pivot table', function () {
    $workspace = Workspace::factory()->create();
    $user = User::factory()->create();

    $workspace->users()->attach($user, ['role' => WorkspaceRole::Owner->value]);

    expect($workspace->hasMember($user))->toBeTrue()
        ->and($workspace->roleFor($user))->toBe(WorkspaceRole::Owner)
        ->and($user->workspaces()->first()->is($workspace))->toBeTrue();
});

it('reports no membership for a user outside the workspace', function () {
    $workspace = Workspace::factory()->create();
    $outsider = User::factory()->create();

    expect($workspace->hasMember($outsider))->toBeFalse()
        ->and($workspace->roleFor($outsider))->toBeNull();
});

it('cascade-deletes a workspace\'s sites when the workspace is deleted', function () {
    $workspace = Workspace::factory()->create();
    $site = Site::factory()->for($workspace)->create();

    $workspace->delete();

    $this->assertDatabaseMissing('sites', ['id' => $site->id]);
});

it('relates a site to its analytics snapshots and posts', function () {
    $site = Site::factory()->create();
    $post = Post::factory()->for($site)->create();
    $snapshot = AnalyticsSnapshot::factory()->for($site)->create();

    expect($site->posts->first()->is($post))->toBeTrue()
        ->and($site->analyticsSnapshots->first()->is($snapshot))->toBeTrue()
        ->and($snapshot->site->is($site))->toBeTrue();
});

it('relates a post to its publishing jobs', function () {
    $post = Post::factory()->create();
    $job = PublishingJob::factory()->for($post)->create();

    expect($post->publishingJobs->first()->is($job))->toBeTrue()
        ->and($job->post->is($post))->toBeTrue();
});

it('enforces one analytics snapshot per site per day', function () {
    $site = Site::factory()->create();
    AnalyticsSnapshot::factory()->for($site)->create(['snapshot_date' => '2026-01-01']);

    AnalyticsSnapshot::factory()->for($site)->create(['snapshot_date' => '2026-01-01']);
})->throws(\Illuminate\Database\QueryException::class);

it('scopes sites to only connected via Site::connected()', function () {
    Site::factory()->count(2)->create();
    Site::factory()->disconnected()->count(3)->create();

    expect(Site::query()->connected()->count())->toBe(2);
});

it('scopes posts to published vs. unpublished via Post scopes', function () {
    $site = Site::factory()->create();
    Post::factory()->for($site)->published()->count(2)->create();
    Post::factory()->for($site)->count(1)->create(); // draft
    Post::factory()->for($site)->inReview()->count(1)->create();

    expect(Post::query()->published()->count())->toBe(2)
        ->and(Post::query()->unpublished()->count())->toBe(2);
});
