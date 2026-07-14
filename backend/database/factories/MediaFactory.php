<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'source' => 'upload',
            'disk' => 'public',
            'storage_path' => 'media/1/'.fake()->uuid().'.jpg',
            'filename' => fake()->word().'.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1000, 500000),
            'width' => 800,
            'height' => 600,
            'hash' => hash('sha256', fake()->uuid()),
            'alt_text' => fake()->sentence(4),
        ];
    }
}
