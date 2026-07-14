<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteCredentialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'wp_username' => 'admin',
            'application_password' => fake()->regexify('[A-Za-z0-9]{4} [A-Za-z0-9]{4} [A-Za-z0-9]{4} [A-Za-z0-9]{4} [A-Za-z0-9]{4} [A-Za-z0-9]{4}'),
        ];
    }
}
