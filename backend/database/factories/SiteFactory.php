<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $storageLimitMb = 10240;

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company().' Blog',
            'status' => SiteStatus::Connected,
            'wordpress_version' => '6.'.fake()->numberBetween(5, 8).'.'.fake()->numberBetween(0, 3),
            'theme' => fake()->randomElement([
                'Twenty Twenty-Five', 'Twenty Twenty-Four', 'Astra', 'GeneratePress',
            ]),
            'plugin_updates_available' => fake()->numberBetween(0, 6),
            'storage_used_mb' => fake()->numberBetween(200, $storageLimitMb - 500),
            'storage_limit_mb' => $storageLimitMb,
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => ['status' => SiteStatus::Disconnected]);
    }
}
