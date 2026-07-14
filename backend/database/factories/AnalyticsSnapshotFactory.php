<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnalyticsSnapshotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'snapshot_date' => fake()->unique()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'visitors' => fake()->numberBetween(50, 2000),
            'posts_published' => fake()->numberBetween(0, 2),
            'storage_used_mb' => fake()->numberBetween(200, 9000),
        ];
    }
}
