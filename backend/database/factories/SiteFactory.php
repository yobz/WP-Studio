<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Site;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        $storageLimitMb = 10240;

        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company().' Blog',
            'url' => 'https://'.fake()->domainName(),
            'status' => SiteStatus::Connected,
            'wordpress_version' => '6.'.fake()->numberBetween(5, 8).'.'.fake()->numberBetween(0, 3),
            'theme' => fake()->randomElement([
                'Twenty Twenty-Five', 'Twenty Twenty-Four', 'Astra', 'GeneratePress',
            ]),
            'php_version' => '8.'.fake()->numberBetween(1, 3),
            'plugin_updates_available' => fake()->numberBetween(0, 6),
            'plugin_count' => fake()->numberBetween(5, 40),
            'user_count' => fake()->numberBetween(1, 10),
            'timezone' => 'America/New_York',
            'language' => 'en_US',
            'storage_used_mb' => fake()->numberBetween(200, $storageLimitMb - 500),
            'storage_limit_mb' => $storageLimitMb,
            'last_connected_at' => now(),
            'last_checked_at' => now(),
        ];
    }

    public function disconnected(): static
    {
        return $this->state(fn () => ['status' => SiteStatus::Disconnected]);
    }

    public function withError(): static
    {
        return $this->state(fn () => [
            'status' => SiteStatus::Error,
            'connection_error' => 'The WordPress site rejected the supplied credentials.',
        ]);
    }
}
