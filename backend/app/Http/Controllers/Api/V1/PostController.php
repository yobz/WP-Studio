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
use Illuminate\Http\JsonResponse;

/**
 * Real CRUD over `Post` records (Milestone 7) — Milestone 6's
 * `IndexPostsRequest` filters are now actually applied, not just
 * validated. Pagination isn't implemented yet (see
 * docs/adr/0005-domain-model.md's Performance section) — acceptable at
 * today's seeded data volume, flagged as a real future need, not
 * ignored.
 */
class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    public function index(IndexPostsRequest $request): JsonResponse
    {
        $query = Post::query();

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
        return ApiResponse::success(data: new PostResource($post));
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $site = Site::findOrFail($request->validated('site_id'));
        $post = $this->posts->create($site, $request->safe()->except('site_id'));

        return ApiResponse::success(data: new PostResource($post), status: 201);
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $post = $this->posts->update($post, $request->validated());

        return ApiResponse::success(data: new PostResource($post));
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->posts->delete($post);

        return ApiResponse::success(data: null, status: 200);
    }
}
