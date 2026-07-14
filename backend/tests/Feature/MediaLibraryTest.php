<?php

use App\Enums\WorkspaceRole;
use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

// A real, minimal 1x1 PNG — GD is not installed in this environment, so
// dimension extraction (getimagesizefromstring) is exercised against real
// image bytes rather than Illuminate's GD-backed UploadedFile::fake()->image().
const ONE_PIXEL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

it('uploads a file and creates a media record with real metadata', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $file = UploadedFile::fake()->createWithContent('cover.png', base64_decode(ONE_PIXEL_PNG_BASE64));

    $response = $this->postJson('/api/v1/media', [
        'file' => $file,
        'alt_text' => 'A scenic cover photo',
    ]);

    $response->assertStatus(201)->assertJson([
        'data' => [
            'source' => 'upload',
            'filename' => 'cover.png',
            'mime_type' => 'image/png',
            'width' => 1,
            'height' => 1,
            'alt_text' => 'A scenic cover photo',
        ],
    ]);

    $media = Media::query()->sole();
    expect($media->workspace_id)->toBe($workspace->id)
        ->and($media->hash)->not->toBeNull();
    Storage::disk('public')->assertExists($media->storage_path);
});

it('rejects an upload with a disallowed file type', function () {
    actingAsWorkspaceMember();
    $file = UploadedFile::fake()->create('script.php', 10, 'application/x-php');

    $response = $this->postJson('/api/v1/media', ['file' => $file]);

    $response->assertStatus(422)->assertJson(['error' => ['code' => 'VALIDATION_FAILED']]);
    expect($response->json('error.details'))->toHaveKey('file');
});

it('rejects an upload larger than the configured limit', function () {
    actingAsWorkspaceMember();
    $file = UploadedFile::fake()->create('huge.jpg', config('media.max_upload_kb') + 100, 'image/jpeg');

    $response = $this->postJson('/api/v1/media', ['file' => $file]);

    $response->assertStatus(422)->assertJson(['error' => ['code' => 'VALIDATION_FAILED']]);
    expect($response->json('error.details'))->toHaveKey('file');
});

it('does not write the same file bytes to disk twice — reuses the existing storage path by hash', function () {
    [, $workspace] = actingAsWorkspaceMember();

    $bytes = base64_decode(ONE_PIXEL_PNG_BASE64);
    $first = UploadedFile::fake()->createWithContent('a.png', $bytes);
    $this->postJson('/api/v1/media', ['file' => $first])->assertStatus(201);

    $second = UploadedFile::fake()->createWithContent('a.png', $bytes);
    $this->postJson('/api/v1/media', ['file' => $second])->assertStatus(201);

    expect(Media::query()->where('workspace_id', $workspace->id)->count())->toBe(2);
    $paths = Media::query()->pluck('storage_path')->unique();
    expect($paths)->toHaveCount(1);
});

it('lists only the current workspace\'s media', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Media::factory()->for($workspace)->count(2)->create();
    Media::factory()->create();

    $response = $this->getJson('/api/v1/media');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('filters the media list by source', function () {
    [, $workspace] = actingAsWorkspaceMember();
    Media::factory()->for($workspace)->create(['source' => 'upload']);
    Media::factory()->for($workspace)->create(['source' => 'wordpress']);

    $response = $this->getJson('/api/v1/media?source=wordpress');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.source'))->toBe('wordpress');
});

it('updates alt text', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $media = Media::factory()->for($workspace)->create(['alt_text' => 'Old text']);

    $response = $this->patchJson("/api/v1/media/{$media->id}", ['alt_text' => 'New text']);

    $response->assertOk()->assertJson(['data' => ['alt_text' => 'New text']]);
});

it('deletes a media item', function () {
    [, $workspace] = actingAsWorkspaceMember();
    $media = Media::factory()->for($workspace)->create();

    $this->deleteJson("/api/v1/media/{$media->id}")->assertOk();

    expect(Media::query()->find($media->id))->toBeNull();
});

it('cannot view, update, or delete media belonging to another workspace', function () {
    actingAsWorkspaceMember();
    $otherMedia = Media::factory()->create();

    $this->getJson("/api/v1/media/{$otherMedia->id}")->assertForbidden();
    $this->patchJson("/api/v1/media/{$otherMedia->id}", ['alt_text' => 'x'])->assertForbidden();
    $this->deleteJson("/api/v1/media/{$otherMedia->id}")->assertForbidden();
});

it('lets any workspace member upload and view media, but only owners/admins delete it', function () {
    [, $workspace] = actingAsWorkspaceMember(role: WorkspaceRole::Member);
    $file = UploadedFile::fake()->create('member-upload.jpg', 50, 'image/jpeg');

    $this->postJson('/api/v1/media', ['file' => $file])->assertStatus(201);

    $media = Media::query()->sole();
    $this->deleteJson("/api/v1/media/{$media->id}")->assertForbidden();
});
