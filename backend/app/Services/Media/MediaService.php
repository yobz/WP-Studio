<?php

namespace App\Services\Media;

use App\Models\Media;
use App\Models\Post;
use App\Models\Site;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Media\DTO\StoredFile;
use App\Services\Media\Exceptions\MediaDownloadException;
use App\Services\WordPress\Security\UrlSafetyValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaService
{
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly UrlSafetyValidator $urlSafety,
    ) {}

    public function storeUpload(UploadedFile $file, Workspace $workspace, ?User $uploadedBy, ?string $altText = null): Media
    {
        $bytes = $file->get();
        $extension = Str::lower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        [$width, $height] = $this->imageDimensions($bytes);
        $stored = $this->store($bytes, $workspace, $extension);

        return Media::create([
            'workspace_id' => $workspace->id,
            'uploaded_by' => $uploadedBy?->id,
            'source' => 'upload',
            'disk' => $stored->disk,
            'storage_path' => $stored->storagePath,
            'filename' => $file->getClientOriginalName(),
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => $file->getSize() ?: strlen($bytes),
            'width' => $width,
            'height' => $height,
            'hash' => hash('sha256', $bytes),
            'alt_text' => $altText,
        ]);
    }

    public function downloadForWordPressPost(Post $post, Site $site, array $remoteMedia): Media
    {
        $sourceUrl = $remoteMedia['source_url'] ?? null;
        if (! is_string($sourceUrl) || $sourceUrl === '') {
            throw new MediaDownloadException('the media item had no source URL.');
        }

        $this->urlSafety->assertSafe($sourceUrl);

        $response = Http::connectTimeout(5)->timeout(15)->get($sourceUrl);
        if ($response->failed()) {
            throw new MediaDownloadException("received HTTP {$response->status()} while downloading the file.");
        }

        $bytes = $response->body();
        $mimeType = is_string($remoteMedia['mime_type'] ?? null) ? $remoteMedia['mime_type'] : (string) $response->header('Content-Type');
        $extension = self::MIME_EXTENSIONS[$mimeType] ?? Str::lower(pathinfo(parse_url($sourceUrl, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION)) ?: 'bin';

        $workspace = $site->workspace;
        $details = is_array($remoteMedia['media_details'] ?? null) ? $remoteMedia['media_details'] : [];
        $stored = $this->store($bytes, $workspace, $extension);

        return $post->featuredImage()->create([
            'workspace_id' => $workspace->id,
            'site_id' => $site->id,
            'collection' => 'featured_image',
            'source' => 'wordpress',
            'source_id' => (string) ($remoteMedia['id'] ?? ''),
            'disk' => $stored->disk,
            'storage_path' => $stored->storagePath,
            'original_url' => $sourceUrl,
            'filename' => basename(parse_url($sourceUrl, PHP_URL_PATH) ?: 'media') ?: 'media',
            'extension' => $extension,
            'mime_type' => $mimeType,
            'size' => strlen($bytes),
            'width' => is_numeric($details['width'] ?? null) ? (int) $details['width'] : null,
            'height' => is_numeric($details['height'] ?? null) ? (int) $details['height'] : null,
            'hash' => hash('sha256', $bytes),
            'alt_text' => is_string($remoteMedia['alt_text'] ?? null) && $remoteMedia['alt_text'] !== '' ? $remoteMedia['alt_text'] : null,
        ]);
    }

    /**
     * Writes bytes to disk only if no existing Media row in this workspace
     * already stores the same content — reuses the existing storage_path
     * instead, so the same file is never written to disk twice.
     */
    private function store(string $bytes, Workspace $workspace, string $extension): StoredFile
    {
        $disk = config('media.disk');
        $hash = hash('sha256', $bytes);

        $existing = Media::query()
            ->where('workspace_id', $workspace->id)
            ->where('hash', $hash)
            ->first();

        if ($existing !== null) {
            return new StoredFile($existing->disk, $existing->storage_path);
        }

        $storagePath = sprintf('media/%d/%s.%s', $workspace->id, (string) Str::uuid(), $extension);
        Storage::disk($disk)->put($storagePath, $bytes);

        return new StoredFile($disk, $storagePath);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function imageDimensions(string $bytes): array
    {
        $info = @getimagesizefromstring($bytes);

        if ($info === false) {
            return [null, null];
        }

        return [$info[0] ?? null, $info[1] ?? null];
    }
}
