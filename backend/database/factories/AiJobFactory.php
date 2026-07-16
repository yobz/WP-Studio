<?php

namespace Database\Factories;

use App\Enums\AiJobStatus;
use App\Models\AiJob;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiJobFactory extends Factory
{
    protected $model = AiJob::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(12),
            'status' => AiJobStatus::Pending,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => AiJobStatus::Completed,
            'result' => fake()->paragraphs(3, true),
            'model' => 'claude-opus-4-8',
            'input_tokens' => fake()->numberBetween(20, 200),
            'output_tokens' => fake()->numberBetween(100, 800),
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => AiJobStatus::Failed,
            'error_message' => 'The AI service is temporarily unavailable — please try again shortly.',
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
