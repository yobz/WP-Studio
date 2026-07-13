<?php

use App\Models\Site;
use App\Models\Workspace;

/**
 * `assertJsonValidationErrors()` assumes Laravel's default error shape
 * (`{"errors": {...}}`); this API's envelope nests validation details
 * under `error.details` instead (see App\Http\Support\ApiResponse),
 * so these assert against that path directly.
 */
it('rejects creating a site without a name', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/sites', ['workspace_id' => $workspace->id]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => ['code' => 'VALIDATION_FAILED'],
    ])->assertJsonPath('error.details.name.0', 'The name field is required.');
});

it('rejects creating a site with a non-existent workspace_id', function () {
    $response = $this->postJson('/api/v1/sites', [
        'workspace_id' => 999999,
        'name' => 'Orphan Site',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('workspace_id');
});

it('rejects an invalid site status', function () {
    $workspace = Workspace::factory()->create();

    $response = $this->postJson('/api/v1/sites', [
        'workspace_id' => $workspace->id,
        'name' => 'Test Site',
        'status' => 'not-a-real-status',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('status');
});

it('rejects updating a site with an out-of-range plugin_updates_available', function () {
    $site = Site::factory()->create();

    $response = $this->putJson("/api/v1/sites/{$site->id}", [
        'plugin_updates_available' => -1,
    ]);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('plugin_updates_available');
});

it('rejects creating a post without a site_id', function () {
    $response = $this->postJson('/api/v1/posts', ['title' => 'Orphan Post']);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('site_id');
});

it('rejects an invalid post status filter on the index endpoint', function () {
    $response = $this->getJson('/api/v1/posts?status=bogus');

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => ['code' => 'VALIDATION_FAILED'],
    ]);
});

it('accepts a valid post status filter on the index endpoint', function () {
    $response = $this->getJson('/api/v1/posts?status=draft');

    $response->assertOk();
});
