<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\IndexPostsRequest;
use App\Http\Requests\V1\StorePostRequest;
use App\Http\Requests\V1\UpdatePostRequest;
use App\Http\Resources\V1\PostResource;
use App\Http\Support\ApiResponse;
use App\Models\Post;
use App\Models\Site;
use App\Services\PostService;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Http\JsonResponse;

class PostController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly CurrentWorkspaceContext $workspaceContext,
    ) {}

    public function index(IndexPostsRequest $request): JsonResponse
    {
        $workspace = $this->workspaceContext->get();

        $query = Post::query()->whereIn('site_id', $workspace->sites()->pluck('id'));

        if ($siteId = $request->validated('site_id')) {
            $query->where('site_id', $siteId);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success(
            data: PostResource::collection($query->latest()->get()),
        );
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);

        return ApiResponse::success(data: new PostResource($post));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $site = Site::findOrFail($request->validated('site_id'));
        $this->authorize('create', [Post::class, $site]);

        $post = $this->posts->create($site, $request->safe()->except('site_id'));

        return ApiResponse::success(data: new PostResource($post), status: 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);

        $post = $this->posts->update($post, $request->validated());

        return ApiResponse::success(data: new PostResource($post));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);

        $this->posts->delete($post);

        return ApiResponse::success(data: null, status: 200);
    }
}
