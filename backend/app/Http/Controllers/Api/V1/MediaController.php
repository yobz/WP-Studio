<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexMediaRequest;
use App\Http\Requests\V1\StoreMediaRequest;
use App\Http\Requests\V1\UpdateMediaRequest;
use App\Http\Resources\V1\MediaResource;
use App\Http\Support\ApiResponse;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(
        private readonly MediaService $media,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(IndexMediaRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();
        $this->authorize('viewAny', [Media::class, $workspace]);

        $query = Media::query()->where('workspace_id', $workspace->id);

        if ($source = $request->validated('source')) {
            $query->where('source', $source);
        }

        if ($mimeType = $request->validated('mime_type')) {
            $query->where('mime_type', $mimeType);
        }

        return ApiResponse::success(
            data: MediaResource::collection($query->latest()->get()),
        );
    }

    public function store(StoreMediaRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();
        $this->authorize('create', [Media::class, $workspace]);

        $media = $this->media->storeUpload(
            $request->file('file'),
            $workspace,
            $request->user(),
            $request->validated('alt_text'),
        );

        return ApiResponse::success(data: new MediaResource($media), status: 201);
    }

    public function show(Media $media): JsonResponse
    {
        $this->authorize('view', $media);

        return ApiResponse::success(data: new MediaResource($media));
    }

    public function update(UpdateMediaRequest $request, Media $media): JsonResponse
    {
        $this->authorize('update', $media);

        $media->update($request->validated());

        return ApiResponse::success(data: new MediaResource($media));
    }

    public function destroy(Media $media): JsonResponse
    {
        $this->authorize('delete', $media);

        $media->delete();

        return ApiResponse::success(data: null);
    }
}
