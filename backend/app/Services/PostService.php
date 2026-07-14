<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Site;

class PostService
{
    public function create(Site $site, array $attributes): Post
    {
        $post = $site->posts()->create($attributes);

        return $post;
    }

    public function update(Post $post, array $attributes): Post
    {
        $post->update($attributes);

        return $post;
    }

    public function delete(Post $post): void
    {
        $post->delete();
    }
}
