<?php

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Workspace;

it('lists sites', function () {
    Site::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/sites');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters sites by workspace_id and status', function () {
    $workspace = Workspace::factory()->create();
    Site::factory()->create(['workspace_id' => $workspace->id, 'status' => SiteStatus::Connected]);
    Site::factory()->disconnected()->create(['workspace_id' => $workspace->id]);
    Site::factory()->create(); // different workspace

    $response = $this->getJson("/api/v1/sites?workspace_id={$workspace->id}&status=connected");

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('shows a single site', function () {
    $site = Site::factory()->create();

    $response = $this->getJson("/api/v1/sites/{$site->id}");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['id' => $site->id, 'name' => $site->name],
    ]);
});

it('returns a 404 envelope for a missing site', function () {
    $response = $this->getJson('/api/v1/sites/999999');

    $response->assertNotFound()->assertJson([
        'success' => false,
        'error' => ['code' => 'NOT_FOUND'],
    ]);
});

it('creates a site within a workspace', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/sites', [
        'workspace_id' => $workspace->id,
        'name' => 'New Site',
        'status' => 'connected',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => ['name' => 'New Site', 'workspace_id' => $workspace->id],
    ]);
    $this->assertDatabaseHas('sites', ['name' => 'New Site', 'workspace_id' => $workspace->id]);
});

it('updates a site without allowing workspace_id to change', function () {
    $originalWorkspace = Workspace::factory()->create();
    $otherWorkspace = Workspace::factory()->create();
    $site = Site::factory()->create(['workspace_id' => $originalWorkspace->id, 'name' => 'Old Name']);

    $response = $this->putJson("/api/v1/sites/{$site->id}", [
        'name' => 'Updated Name',
        'workspace_id' => $otherWorkspace->id, // not a validated field — must be ignored
    ]);

    $response->assertOk()->assertJson(['data' => ['name' => 'Updated Name']]);
    expect($site->refresh()->workspace_id)->toBe($originalWorkspace->id);
});

it('soft deletes a site, excluding it from the index but keeping the row', function () {
    $site = Site::factory()->create();

    $response = $this->deleteJson("/api/v1/sites/{$site->id}");

    $response->assertOk();
    $this->assertSoftDeleted('sites', ['id' => $site->id]);
    $this->getJson('/api/v1/sites')->assertJsonCount(0, 'data');
});
