<?php

use App\Enums\AiJobStatus;
use App\Enums\WorkspaceRole;
use App\Models\AiJob;
use App\Services\AI\Contracts\AiClientContract;
use App\Services\AI\DTO\AiGenerationResult;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Exceptions\AiResponseException;

class FakeSucceedingAiClient implements AiClientContract
{
    public function generate(string $prompt): AiGenerationResult
    {
        return new AiGenerationResult(
            content: "Draft in response to: {$prompt}",
            model: 'claude-opus-4-8',
            inputTokens: 42,
            outputTokens: 256,
        );
    }
}

class FakeFailingAiClient implements AiClientContract
{
    public function __construct(private readonly Exception $exception) {}

    public function generate(string $prompt): AiGenerationResult
    {
        throw $this->exception;
    }
}

it('requires authentication to generate content', function () {
    $this->postJson('/api/v1/ai/generate', ['prompt' => 'Write a blog post about WordPress security'])
        ->assertStatus(401);
});

it('validates the prompt', function () {
    actingAsWorkspaceMember();

    $this->postJson('/api/v1/ai/generate', ['prompt' => ''])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'VALIDATION_FAILED']]);
});

it('rejects a whitespace-only prompt after trimming', function () {
    actingAsWorkspaceMember();

    $this->postJson('/api/v1/ai/generate', ['prompt' => '     '])
        ->assertStatus(422)
        ->assertJson(['error' => ['code' => 'VALIDATION_FAILED']]);
});

it('queues a generation job and completes it with real content from the provider', function () {
    [$user, $workspace] = actingAsWorkspaceMember();
    $this->app->instance(AiClientContract::class, new FakeSucceedingAiClient);

    $response = $this->postJson('/api/v1/ai/generate', [
        'prompt' => 'Write a blog post about WordPress security best practices',
    ]);

    $response->assertStatus(202)->assertJson(['data' => ['status' => 'queued']]);
    $jobId = $response->json('data.job_id');

    $job = AiJob::query()->findOrFail($jobId);
    expect($job->workspace_id)->toBe($workspace->id)
        ->and($job->user_id)->toBe($user->id)
        ->and($job->status)->toBe(AiJobStatus::Completed)
        ->and($job->result)->toContain('Draft in response to')
        ->and($job->model)->toBe('claude-opus-4-8')
        ->and($job->input_tokens)->toBe(42)
        ->and($job->output_tokens)->toBe(256);

    $show = $this->getJson("/api/v1/ai/jobs/{$jobId}");
    $show->assertOk()->assertJson([
        'data' => ['status' => 'completed', 'input_tokens' => 42, 'output_tokens' => 256],
    ]);
});

it('marks the job failed without bubbling an error when the provider returns something unusable', function () {
    actingAsWorkspaceMember();
    $this->app->instance(
        AiClientContract::class,
        new FakeFailingAiClient(new AiResponseException('the response did not include any generated text.')),
    );

    $response = $this->postJson('/api/v1/ai/generate', ['prompt' => 'Summarize my last 5 posts']);

    $response->assertStatus(202);
    $job = AiJob::query()->findOrFail($response->json('data.job_id'));
    expect($job->status)->toBe(AiJobStatus::Failed)
        ->and($job->error_message)->not->toBeNull();
});

it('surfaces a clean error when the AI provider is unreachable', function () {
    actingAsWorkspaceMember();
    $this->app->instance(
        AiClientContract::class,
        new FakeFailingAiClient(new AiProviderException('could not reach the AI provider.')),
    );

    $response = $this->postJson('/api/v1/ai/generate', ['prompt' => 'Suggest 5 SEO titles for a post about site speed']);

    $response->assertStatus(503)->assertJson(['error' => ['code' => 'AI_PROVIDER_UNAVAILABLE']]);

    $job = AiJob::query()->sole();
    expect($job->status)->toBe(AiJobStatus::Failed);
});

it('cannot view a generation job belonging to another workspace', function () {
    actingAsWorkspaceMember();
    $otherJob = AiJob::factory()->completed()->create();

    $this->getJson("/api/v1/ai/jobs/{$otherJob->id}")->assertForbidden();
});

it('lets any workspace member generate content, not just owners/admins', function () {
    actingAsWorkspaceMember(role: WorkspaceRole::Member);
    $this->app->instance(AiClientContract::class, new FakeSucceedingAiClient);

    $this->postJson('/api/v1/ai/generate', ['prompt' => 'Write a short WordPress maintenance checklist'])
        ->assertStatus(202);
});
