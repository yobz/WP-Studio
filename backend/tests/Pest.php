<?php

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

function actingAsWorkspaceMember(?Workspace $workspace = null, WorkspaceRole $role = WorkspaceRole::Owner): array
{
    $workspace ??= Workspace::factory()->create();
    $user = User::factory()->create();
    $workspace->users()->attach($user, ['role' => $role->value]);

    test()->actingAs($user);

    return [$user, $workspace];
}

function fakeSuccessfulWordPressConnection(): void
{
    Http::fake([
        '*/wp-json/' => Http::response(['name' => 'Test Site', 'description' => 'Just another WordPress site']),
        '*/wp-json/wp/v2/settings' => Http::response(['title' => 'Test Site', 'timezone' => 'America/New_York', 'language' => 'en_US']),
        '*/wp-json/wp/v2/themes' => Http::response([
            ['status' => 'inactive', 'name' => ['rendered' => 'Twenty Twenty-Four']],
            ['status' => 'active', 'name' => ['rendered' => 'Twenty Twenty-Five']],
        ]),
        '*/wp-json/wp/v2/plugins' => Http::response(array_fill(0, 12, ['status' => 'active'])),
        '*/wp-json/wp/v2/users*' => Http::response(
            [['id' => 1, 'name' => 'Admin']],
            200,
            ['X-WP-Total' => '4'],
        ),
    ]);
}

function fakeWordPressConnectionWithLimitedCapabilities(): void
{
    Http::fake([
        '*/wp-json/' => Http::response(['name' => 'Test Site']),
        '*/wp-json/wp/v2/settings' => Http::response(['timezone' => 'UTC', 'language' => 'en_US']),
        '*/wp-json/wp/v2/themes' => Http::response(['code' => 'rest_cannot_manage_themes'], 403),
        '*/wp-json/wp/v2/plugins' => Http::response(['code' => 'rest_cannot_view_plugins'], 403),
        '*/wp-json/wp/v2/users*' => Http::response(['code' => 'rest_user_cannot_view'], 403),
    ]);
}

function fakeWordPressConnectionRejectsCredentials(): void
{
    Http::fake([
        '*/wp-json/' => Http::response(['name' => 'Test Site']),
        '*/wp-json/wp/v2/settings' => Http::response(['code' => 'rest_forbidden'], 401),
    ]);
}

function fakeWordPressConnectionUnreachable(): void
{
    Http::fake([
        '*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('Connection timed out'),
    ]);
}

function fakeWordPressConnectionReturnsMalformedResponse(): void
{
    Http::fake([
        '*/wp-json/' => Http::response('<html>Not JSON</html>', 200, ['Content-Type' => 'text/html']),
    ]);
}
