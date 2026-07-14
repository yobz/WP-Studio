<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => fake()->sentence(6),
            'status' => PostStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
        ]);
    }

    public function inReview(): static
    {
        return $this->state(fn () => [
            'status' => PostStatus::InReview,
            'published_at' => null,
        ]);
    }

    public function syncedFromWordPress(): static
    {
        return $this->state(fn () => [
            'wordpress_post_id' => fake()->unique()->numberBetween(1, 100000),
            'wordpress_modified_at' => fake()->dateTimeBetween('-6 months', 'now'),
            'wordpress_url' => fake()->url(),
            'sync_status' => 'synced',
            'sync_hash' => hash('sha256', fake()->uuid()),
            'last_synced_at' => now(),
        ]);
    }
}
