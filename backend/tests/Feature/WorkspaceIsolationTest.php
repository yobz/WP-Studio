<?php

use App\Enums\WorkspaceRole;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;

it('rejects an unauthenticated request to a workspace-scoped endpoint', function () {
    $this->getJson('/api/v1/sites')->assertUnauthorized()->assertJson([
        'success' => false,
        'error' => ['code' => 'UNAUTHENTICATED'],
    ]);
});

it('403s a request for a workspace the user is not a member of', function () {
    actingAsWorkspaceMember();
    $otherWorkspace = Workspace::factory()->create();

    $response = $this->getJson("/api/v1/sites?workspace_id={$otherWorkspace->id}");

    $response->assertForbidden()->assertJson([
        'success' => false,
        'error' => ['code' => 'FORBIDDEN'],
    ]);
});

it('403s identically for a nonexistent workspace id as for one the user is not a member of', function () {
    actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/sites?workspace_id=999999');

    $response->assertForbidden();
});

it('cannot view a site belonging to another workspace by guessing its id', function () {
    actingAsWorkspaceMember();
    $otherSite = Site::factory()->create();

    $this->getJson("/api/v1/sites/{$otherSite->id}")->assertForbidden();
});

it('cannot attach a post to a site in another workspace', function () {
    actingAsWorkspaceMember();
    $otherSite = Site::factory()->create();

    $response = $this->postJson('/api/v1/posts', [
        'site_id' => $otherSite->id,
        'title' => 'Smuggled Post',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('posts', ['title' => 'Smuggled Post']);
});

it('only lets an owner or admin create a site, not a plain member', function () {
    [$member, $workspace] = actingAsWorkspaceMember(role: WorkspaceRole::Member);

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'New Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertForbidden();
});

it('switches the resolved workspace via the X-Workspace-Id header for a user in both', function () {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();
    $user = User::factory()->create();
    $workspaceA->users()->attach($user, [
        'role' => WorkspaceRole::Owner->value,
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);
    $workspaceB->users()->attach($user, ['role' => WorkspaceRole::Owner->value]);
    $this->actingAs($user);

    Site::factory()->for($workspaceA)->create();
    Site::factory()->count(2)->for($workspaceB)->create();

    $this->getJson('/api/v1/sites')->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/sites', ['X-Workspace-Id' => (string) $workspaceB->id])
        ->assertJsonCount(2, 'data');
});

it('isolates the dashboard summary to the current workspace only', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $mySite = Site::factory()->for($workspace)->create(['storage_used_mb' => 100, 'storage_limit_mb' => 1000]);
    Post::factory()->for($mySite)->published()->create();

    $otherSite = Site::factory()->create(['storage_used_mb' => 9999, 'storage_limit_mb' => 9999]);
    Post::factory()->for($otherSite)->published()->count(5)->create();

    $response = $this->getJson('/api/v1/dashboard/summary');

    $response->assertOk()->assertJson([
        'data' => [
            'connected_sites' => 1,
            'published_posts' => 1,
            'storage_used_mb' => 100,
            'storage_limit_mb' => 1000,
        ],
    ]);
});
