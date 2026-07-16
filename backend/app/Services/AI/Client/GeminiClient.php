<?php

namespace App\Services\AI\Client;

use App\Services\AI\Contracts\AiClientContract;
use App\Services\AI\DTO\AiGenerationResult;
use App\Services\AI\Exceptions\AiConfigurationException;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Exceptions\AiResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiClient implements AiClientContract
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private const CONNECT_TIMEOUT_SECONDS = 5;

    private const REQUEST_TIMEOUT_SECONDS = 30;

    public function generate(string $prompt): AiGenerationResult
    {
        $apiKey = config('ai.gemini.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new AiConfigurationException('GEMINI_API_KEY is not configured.');
        }

        $model = config('ai.gemini.model');

        $response = $this->request($apiKey, $model, $prompt);
        $body = $this->assertSuccessfulResponse($response);

        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new AiResponseException('the response did not include any generated text.');
        }

        $usage = $body['usageMetadata'] ?? [];

        return new AiGenerationResult(
            content: $text,
            model: is_string($body['modelVersion'] ?? null) ? $body['modelVersion'] : $model,
            inputTokens: is_int($usage['promptTokenCount'] ?? null) ? $usage['promptTokenCount'] : null,
            outputTokens: is_int($usage['candidatesTokenCount'] ?? null) ? $usage['candidatesTokenCount'] : null,
        );
    }

    private function request(string $apiKey, string $model, string $prompt): ?Response
    {
        try {
            return Http::withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->retry(2, 200, when: fn (Throwable $e) => $e instanceof ConnectionException, throw: false)
                ->post(self::BASE_URL."/models/{$model}:generateContent", [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => config('ai.max_tokens'),
                    ],
                ]);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function assertSuccessfulResponse(?Response $response): array
    {
        if ($response === null) {
            throw new AiProviderException('could not reach the AI provider.');
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiConfigurationException('the Gemini API key was rejected.');
        }

        if ($response->status() === 429) {
            throw new AiProviderException('rate limit exceeded.');
        }

        if ($response->failed()) {
            throw new AiProviderException("received HTTP {$response->status()}.");
        }

        $body = $response->json();
        if (! is_array($body)) {
            throw new AiResponseException('the response was not valid JSON.');
        }

        return $body;
    }
}
