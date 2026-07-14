<?php

use App\Enums\SiteStatus;
use App\Enums\WorkspaceRole;
use App\Models\Site;
use App\Models\SiteCredential;
use Illuminate\Support\Facades\Http;

it('connects a real site and derives its metadata from the handshake', function () {
    actingAsWorkspaceMember();
    fakeSuccessfulWordPressConnection();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'My WordPress Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => [
            'name' => 'My WordPress Site',
            'url' => 'https://example.com',
            'status' => 'connected',
            'theme' => 'Twenty Twenty-Five',
            'plugin_count' => 12,
            'user_count' => 4,
            'timezone' => 'America/New_York',
            'language' => 'en_US',
        ],
    ]);
    expect($response->json('data'))->not->toHaveKey('application_password')
        ->and($response->json('data'))->not->toHaveKey('credential');
});

it('stores the credential encrypted and never as plaintext', function () {
    actingAsWorkspaceMember();
    fakeSuccessfulWordPressConnection();

    $this->postJson('/api/v1/sites', [
        'name' => 'My WordPress Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ])->assertCreated();

    $credential = SiteCredential::query()->sole();
    expect($credential->wp_username)->toBe('admin')
        ->and($credential->application_password)->toBe('abcd efgh ijkl mnop qrst uvwx')
        ->and($credential->getRawOriginal('application_password'))->not->toContain('abcd efgh ijkl mnop qrst uvwx');
});

it('does not create a site when the credentials are rejected', function () {
    actingAsWorkspaceMember();
    fakeWordPressConnectionRejectsCredentials();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'My WordPress Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'wrong wrong wrong wrong wrong',
    ]);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => ['code' => 'WORDPRESS_AUTHENTICATION_FAILED'],
    ]);
    expect(Site::query()->count())->toBe(0);
});

it('reports an unreachable site distinctly from rejected credentials', function () {
    actingAsWorkspaceMember();
    fakeWordPressConnectionUnreachable();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'My WordPress Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertStatus(503)->assertJson([
        'success' => false,
        'error' => ['code' => 'WORDPRESS_UNREACHABLE'],
    ]);
    expect(Site::query()->count())->toBe(0);
});

it('rejects a malformed response from a site claiming to be WordPress', function () {
    actingAsWorkspaceMember();
    fakeWordPressConnectionReturnsMalformedResponse();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'Not Actually WordPress',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertStatus(502)->assertJson([
        'success' => false,
        'error' => ['code' => 'WORDPRESS_INVALID_RESPONSE'],
    ]);
});

it('connects successfully with partial metadata when the credential lacks admin capabilities', function () {
    actingAsWorkspaceMember();
    fakeWordPressConnectionWithLimitedCapabilities();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'Editor-Only Site',
        'url' => 'https://example.com',
        'wp_username' => 'editor',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertCreated()->assertJson([
        'data' => [
            'status' => 'connected',
            'theme' => null,
            'plugin_count' => null,
            'user_count' => null,
            'timezone' => 'UTC',
        ],
    ]);
});

it('rejects a connection attempt to a private network address without making any request', function () {
    actingAsWorkspaceMember();
    Http::fake();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'Suspicious Site',
        'url' => 'http://192.168.1.1/',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertStatus(503)->assertJson([
        'success' => false,
        'error' => ['code' => 'WORDPRESS_UNREACHABLE'],
    ]);
    Http::assertNothingSent();
    expect(Site::query()->count())->toBe(0);
});

it('rejects a connection attempt to localhost', function () {
    actingAsWorkspaceMember();
    Http::fake();

    $this->postJson('/api/v1/sites', [
        'name' => 'Suspicious Site',
        'url' => 'http://localhost:8080/',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ])->assertStatus(503);
    Http::assertNothingSent();
});

it('disconnects a site, deleting its stored credential', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();

    $response = $this->postJson("/api/v1/sites/{$site->id}/disconnect");

    $response->assertOk()->assertJson(['data' => ['status' => 'disconnected']]);
    expect($site->fresh()->credential)->toBeNull();
});

it('verifies an existing connection and updates last_checked_at', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create(['theme' => 'Old Theme']);
    SiteCredential::factory()->for($site)->create();
    fakeSuccessfulWordPressConnection();

    $response = $this->postJson("/api/v1/sites/{$site->id}/verify");

    $response->assertOk()->assertJson(['data' => ['status' => 'connected', 'theme' => 'Twenty Twenty-Five']]);
    expect($site->fresh()->last_checked_at)->not->toBeNull();
});

it('marks a site as errored, with a stored reason, when verification fails', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeWordPressConnectionRejectsCredentials();

    $response = $this->postJson("/api/v1/sites/{$site->id}/verify");

    $response->assertStatus(422);
    $site->refresh();
    expect($site->status)->toBe(SiteStatus::Error)
        ->and($site->connection_error)->not->toBeNull();
});

it('refuses to verify a site with no stored credential', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create();

    $this->postJson("/api/v1/sites/{$site->id}/verify")->assertStatus(503);
});

it('refreshes metadata for an already-connected site', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $site = Site::factory()->for($workspace)->create(['user_count' => 1]);
    SiteCredential::factory()->for($site)->create();
    fakeSuccessfulWordPressConnection();

    $this->postJson("/api/v1/sites/{$site->id}/refresh-metadata")
        ->assertOk()
        ->assertJson(['data' => ['user_count' => 4]]);
});

it('lets any workspace member verify a connection, not just owners/admins', function () {
    [, $workspace] = actingAsWorkspaceMember(role: WorkspaceRole::Member);
    $site = Site::factory()->for($workspace)->create();
    SiteCredential::factory()->for($site)->create();
    fakeSuccessfulWordPressConnection();

    $this->postJson("/api/v1/sites/{$site->id}/verify")->assertOk();
});

it('cannot disconnect a site in another workspace', function () {
    actingAsWorkspaceMember();
    $otherSite = Site::factory()->create();
    SiteCredential::factory()->for($otherSite)->create();

    $this->postJson("/api/v1/sites/{$otherSite->id}/disconnect")->assertForbidden();
    expect($otherSite->fresh()->credential)->not->toBeNull();
});

it('rate limits repeated connection attempts', function () {
    actingAsWorkspaceMember();
    fakeWordPressConnectionRejectsCredentials();

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/v1/sites', [
            'name' => 'Site',
            'url' => 'https://example.com',
            'wp_username' => 'admin',
            'application_password' => 'wrong wrong wrong wrong wrong',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/sites', [
        'name' => 'Site',
        'url' => 'https://example.com',
        'wp_username' => 'admin',
        'application_password' => 'wrong wrong wrong wrong wrong',
    ])->assertStatus(429);
});
