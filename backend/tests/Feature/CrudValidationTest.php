<?php

it('rejects creating a site without a name', function () {
    actingAsWorkspaceMember();

    $response = $this->postJson('/api/v1/sites', []);

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => ['code' => 'VALIDATION_FAILED'],
    ])->assertJsonPath('error.details.name.0', 'The name field is required.');
});

it('rejects connecting a site without a url, username, or application password', function () {
    actingAsWorkspaceMember();

    $response = $this->postJson('/api/v1/sites', ['name' => 'Test Site']);

    $response->assertStatus(422);
    expect($response->json('error.details'))
        ->toHaveKeys(['url', 'wp_username', 'application_password']);
});

it('rejects a malformed url when connecting a site', function () {
    actingAsWorkspaceMember();

    $response = $this->postJson('/api/v1/sites', [
        'name' => 'Test Site',
        'url' => 'not-a-url',
        'wp_username' => 'admin',
        'application_password' => 'abcd efgh ijkl mnop qrst uvwx',
    ]);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('url');
});

it('rejects creating a post without a site_id', function () {
    actingAsWorkspaceMember();

    $response = $this->postJson('/api/v1/posts', ['title' => 'Orphan Post']);

    $response->assertStatus(422);
    expect($response->json('error.details'))->toHaveKey('site_id');
});

it('rejects an invalid post status filter on the index endpoint', function () {
    actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/posts?status=bogus');

    $response->assertStatus(422)->assertJson([
        'success' => false,
        'error' => ['code' => 'VALIDATION_FAILED'],
    ]);
});

it('accepts a valid post status filter on the index endpoint', function () {
    actingAsWorkspaceMember();

    $response = $this->getJson('/api/v1/posts?status=draft');

    $response->assertOk();
});
