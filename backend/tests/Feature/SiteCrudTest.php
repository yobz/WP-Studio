<?php

use App\Models\Site;
use App\Models\Workspace;

it('lists only sites in the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Site::factory()->count(3)->for($workspace)->create();
    Site::factory()->count(2)->create();

    $response = $this->getJson('/api/v1/sites');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters sites by status within the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Site::factory()->create(['workspace_id' => $workspace->id, 'status' => 'connected']);
    Site::factory()->disconnected()->create(['workspace_id' => $workspace->id]);

    $response = $this->getJson('/api/v1/sites?status=connected');

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('shows a single site within the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    $response = $this->getJson("/api/v1/sites/{$site->id}");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['id' => $site->id, 'name' => $site->name],
    ]);
});

it('returns a 404 envelope for a missing site', function () {
    actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/sites/999999');

    $response->assertNotFound()->assertJson([
        'success' => false,
        'error' => ['code' => 'NOT_FOUND'],
    ]);
});

it('creates a site in the current workspace', function () {
    [, $workspace] = actingAsWorkspaceMember();
    fakeSuccessfulWordPressConnection();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'New Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => ['name' => 'New Site', 'workspace_id' => $workspace->id],
    ]);
    $this->assertDatabaseHas('sites', ['name' => 'New Site', 'workspace_id' => $workspace->id]);
});

it('ignores a client-supplied workspace_id when creating a site', function () {
    actingAsWorkspaceMember();
    $otherWorkspace = Workspace::factory()->create();
    fakeSuccessfulWordPressConnection();

    $response = $this->postJson('/api/v1/sites', [
        'workspace_id' => $otherWorkspace->id,
        'name' => 'New Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertCreated();
    expect(Site::where('name', 'New Site')->first()->workspace_id)->not->toBe($otherWorkspace->id);
});

it('updates a site without allowing workspace_id to change', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $otherWorkspace = Workspace::factory()->create();
    $site = Site::factory()->create(['workspace_id' => $workspace->id, 'name' => 'Old Name']);

    $response = $this->putJson("/api/v1/sites/{$site->id}", [
        'name' => 'Updated Name',
        'workspace_id' => $otherWorkspace->id,
    ]);

    $response->assertOk()->assertJson(['data' => ['name' => 'Updated Name']]);
    expect($site->refresh()->workspace_id)->toBe($workspace->id);
});

it('soft deletes a site, excluding it from the index but keeping the row', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    $response = $this->deleteJson("/api/v1/sites/{$site->id}");

    $response->assertOk();
    $this->assertSoftDeleted('sites', ['id' => $site->id]);
    $this->getJson('/api/v1/sites')->assertJsonCount(0, 'data');
});
