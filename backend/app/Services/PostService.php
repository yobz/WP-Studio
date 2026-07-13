<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Site;

class PostService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(Site $site, array $attributes): Post
    {
        /** @var Post $post */
        $post = $site->posts()->create($attributes);

        return $post;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Post $post, array $attributes): Post
    {
        $post->update($attributes);

        return $post;
    }

    public function delete(Post $post): void
    {
        // Soft delete (Post uses SoftDeletes) — see
        // docs/adr/0005-domain-model.md.
        $post->delete();
    }
}
