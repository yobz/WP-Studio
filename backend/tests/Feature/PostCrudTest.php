<?php

use App\Models\Post;
use App\Models\Site;

it('lists posts', function () {
    Post::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/posts');

    $response->assertOk()->assertJsonCount(3, 'data');
});

it('filters posts by site_id and status', function () {
    $site = Site::factory()->create();
    Post::factory()->for($site)->published()->create();
    Post::factory()->for($site)->create(); // draft
    Post::factory()->create(); // different site, published by default state override below
    Post::factory()->published()->create();

    $response = $this->getJson("/api/v1/posts?site_id={$site->id}&status=published");

    $response->assertOk()->assertJsonCount(1, 'data');
});

it('shows a single post', function () {
    $post = Post::factory()->create();

    $response = $this->getJson("/api/v1/posts/{$post->id}");

    $response->assertOk()->assertJson([
        'success' => true,
        'data' => ['id' => $post->id, 'title' => $post->title],
    ]);
});

it('creates a post attached to a site', function () {
    $site = Site::factory()->create();

    $response = $this->postJson('/api/v1/posts', [
        'site_id' => $site->id,
        'title' => 'New Post',
        'status' => 'draft',
    ]);

    $response->assertCreated()->assertJson([
        'success' => true,
        'data' => ['title' => 'New Post', 'site_id' => $site->id],
    ]);
    $this->assertDatabaseHas('posts', ['title' => 'New Post', 'site_id' => $site->id]);
});

it('updates a post', function () {
    $post = Post::factory()->create(['title' => 'Old Title']);

    $response = $this->putJson("/api/v1/posts/{$post->id}", ['title' => 'New Title']);

    $response->assertOk()->assertJson(['data' => ['title' => 'New Title']]);
});

it('soft deletes a post, excluding it from the index but keeping the row', function () {
    $post = Post::factory()->create();

    $response = $this->deleteJson("/api/v1/posts/{$post->id}");

    $response->assertOk();
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
    $this->getJson('/api/v1/posts')->assertJsonCount(0, 'data');
});

it('cascade-deletes a site\'s posts when the site is force-deleted', function () {
    $site = Site::factory()->create();
    $post = Post::factory()->for($site)->create();

    $site->forceDelete();

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});
