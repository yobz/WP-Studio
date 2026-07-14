<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;

it('logs in with valid credentials and starts a real session', function () {
    $user = User::factory()->create(['email' => 'demo@example.com']);

    $response = $this->withHeader('Referer', 'http://localhost:3000')->postJson('/api/v1/login', [
        'email' => 'demo@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['email' => 'demo@example.com'],
    ]);
    $this->assertAuthenticatedAs($user);
});

it('rejects an invalid password without a different error shape than a nonexistent email', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    $wrongPassword = $this->postJson('/api/v1/login', [
        'email' => 'demo@example.com',
        'password' => 'not-the-password',
    ]);
    $unknownEmail = $this->postJson('/api/v1/login', [
        'email' => 'nobody@example.com',
        'password' => 'whatever',
    ]);

    $wrongPassword->assertUnauthorized()->assertJson(['error' => ['code' => 'INVALID_CREDENTIALS']]);
    $unknownEmail->assertUnauthorized()->assertJson(['error' => ['code' => 'INVALID_CREDENTIALS']]);
    $this->assertGuest();
});

it('validates login input shape before attempting authentication', function () {
    $response = $this->postJson('/api/v1/login', ['email' => 'not-an-email']);

    $response->assertStatus(422)->assertJson(['error' => ['code' => 'VALIDATION_FAILED']]);
});

it('logs out and invalidates the session', function () {
    User::factory()->create(['email' => 'demo@example.com']);
    $login = $this->withHeader('Referer', 'http://localhost:3000')->postJson('/api/v1/login', [
        'email' => 'demo@example.com',
        'password' => 'password',
    ])->assertOk();
    $sessionCookie = config('session.cookie');
    $originalSessionId = $login->getCookie($sessionCookie)->getValue();

    $response = $this->withHeader('Referer', 'http://localhost:3000')
        ->withCookie($sessionCookie, $originalSessionId)
        ->postJson('/api/v1/logout');

    $response->assertOk()->assertJson(['success' => true]);

    $newSessionId = $response->getCookie($sessionCookie)->getValue();
    expect($newSessionId)->not->toBe($originalSessionId);
});

it('rejects logout when not authenticated', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});

it('returns the current user with their workspaces on the profile endpoint', function () {
    $workspace = Workspace::factory()->create(['name' => 'Acme']);
    $user = User::factory()->create();
    $workspace->users()->attach($user, ['role' => WorkspaceRole::Owner->value]);
    $this->actingAs($user);

    $response = $this->getJson('/api/v1/user');

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => [
            'email' => $user->email,
            'current_workspace_id' => $workspace->id,
        ],
    ])->assertJsonPath('data.workspaces.0.role', 'owner');
});

it('returns a null current_workspace_id for a user with no workspace yet', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/user');

    $response->assertOk()
        ->assertJsonPath('data.current_workspace_id', null)
        ->assertJsonPath('data.workspaces', []);
});

it('rejects the profile endpoint when unauthenticated', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized()->assertJson([
        'success' => false,
        'error' => ['code' => 'UNAUTHENTICATED'],
    ]);
});

it('rate limits repeated failed login attempts for the same email+IP', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', ['email' => 'demo@example.com', 'password' => 'wrong'])
            ->assertUnauthorized();
    }

    $response = $this->postJson('/api/v1/login', ['email' => 'demo@example.com', 'password' => 'wrong']);

    $response->assertStatus(429)->assertJson(['error' => ['code' => 'RATE_LIMITED']]);
});

it('issues a CSRF cookie from the sanctum endpoint', function () {
    $response = $this->get('/sanctum/csrf-cookie');

    $response->assertNoContent();
    $response->assertCookie('XSRF-TOKEN');
});
