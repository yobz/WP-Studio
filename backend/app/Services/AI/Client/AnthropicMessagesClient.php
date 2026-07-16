<?php

namespace App\Services\AI\Client;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\AI\Contracts\AiClientContract;
use App\Services\AI\DTO\AiGenerationResult;
use App\Services\AI\Exceptions\AiConfigurationException;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Exceptions\AiResponseException;

class AnthropicMessagesClient implements AiClientContract
{
    private readonly Client $client;

    public function __construct()
    {
        $apiKey = config('ai.anthropic.api_key');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new AiConfigurationException('ANTHROPIC_API_KEY is not configured.');
        }

        $this->client = new Client(apiKey: $apiKey);
    }

    public function generate(string $prompt): AiGenerationResult
    {
        try {
            $message = $this->client->messages->create(
                model: config('ai.anthropic.model'),
                maxTokens: config('ai.max_tokens'),
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ],
            );
        } catch (AuthenticationException|PermissionDeniedException $e) {
            throw new AiConfigurationException($e->getMessage());
        } catch (RateLimitException $e) {
            throw new AiProviderException('rate limit exceeded.');
        } catch (APIConnectionException $e) {
            throw new AiProviderException('could not reach the AI provider.');
        } catch (APIStatusException $e) {
            throw new AiProviderException("received HTTP {$e->getCode()}.");
        }

        $content = null;
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $content = $block->text;
                break;
            }
        }

        if ($content === null || trim($content) === '') {
            throw new AiResponseException('the response did not include any generated text.');
        }

        return new AiGenerationResult(
            content: $content,
            model: $message->model,
            inputTokens: $message->usage->inputTokens,
            outputTokens: $message->usage->outputTokens,
        );
    }
}
