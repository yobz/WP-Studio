<?php

namespace App\Jobs;

use App\Enums\AiJobStatus;
use App\Models\AiJob;
use App\Services\AI\Contracts\AiClientContract;
use App\Services\AI\Exceptions\AiConfigurationException;
use App\Services\AI\Exceptions\AiResponseException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateAiContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public readonly AiJob $aiJob) {}

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(AiClientContract $client): void
    {
        $this->aiJob->update(['status' => AiJobStatus::Processing, 'attempted_at' => now()]);

        try {
            $result = $client->generate($this->aiJob->prompt);
        } catch (AiResponseException|AiConfigurationException $e) {
            $this->fail($e);

            return;
        }

        $this->aiJob->update([
            'status' => AiJobStatus::Completed,
            'result' => $result->content,
            'model' => $result->model,
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->aiJob->update([
            'status' => AiJobStatus::Failed,
            'error_message' => $exception?->getMessage() ?? 'The generation job failed after exhausting all retries.',
            'completed_at' => now(),
        ]);
    }
}
