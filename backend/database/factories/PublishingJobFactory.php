<?php

namespace Database\Factories;

use App\Enums\PublishingJobStatus;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

class PublishingJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'post_id' => Post::factory(),
            'status' => PublishingJobStatus::Pending,
            'attempted_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => PublishingJobStatus::Completed,
            'attempted_at' => fake()->dateTimeBetween('-1 week', '-1 day'),
            'completed_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => PublishingJobStatus::Failed,
            'attempted_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'error_message' => 'The WordPress REST API request timed out.',
        ]);
    }
}
