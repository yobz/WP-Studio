<?php

use App\Services\AI\Client\AnthropicMessagesClient;
use App\Services\AI\Client\GeminiClient;
use App\Services\AI\Contracts\AiClientContract;
use App\Services\AI\Exceptions\AiConfigurationException;
use App\Services\AI\Exceptions\AiProviderException;
use App\Services\AI\Exceptions\AiResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['ai.gemini.api_key' => 'test-gemini-key', 'ai.gemini.model' => 'gemini-2.0-flash']);
});

it('generates content from a successful Gemini response', function () {
    Http::fake([
        '*generativelanguage.googleapis.com*' => Http::response([
            'candidates' => [
                ['content' => ['parts' => [['text' => 'Generated draft content.']]]],
            ],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 48],
            'modelVersion' => 'gemini-2.5-flash',
        ]),
    ]);

    $result = (new GeminiClient)->generate('Write a blog post');

    expect($result->content)->toBe('Generated draft content.')
        ->and($result->model)->toBe('gemini-2.5-flash')
        ->and($result->inputTokens)->toBe(12)
        ->and($result->outputTokens)->toBe(48);
});

it('throws a configuration exception when no Gemini key is set', function () {
    config(['ai.gemini.api_key' => null]);

    (new GeminiClient)->generate('Write a blog post');
})->throws(AiConfigurationException::class);

it('throws a configuration exception when Gemini rejects the key', function () {
    Http::fake([
        '*generativelanguage.googleapis.com*' => Http::response(['error' => ['message' => 'API key not valid']], 403),
    ]);

    (new GeminiClient)->generate('Write a blog post');
})->throws(AiConfigurationException::class);

it('throws a provider exception when Gemini rate limits the request', function () {
    Http::fake([
        '*generativelanguage.googleapis.com*' => Http::response(['error' => ['message' => 'quota exceeded']], 429),
    ]);

    (new GeminiClient)->generate('Write a blog post');
})->throws(AiProviderException::class);

it('throws a provider exception when Gemini is unreachable', function () {
    Http::fake([
        '*generativelanguage.googleapis.com*' => fn () => throw new ConnectionException('timed out'),
    ]);

    (new GeminiClient)->generate('Write a blog post');
})->throws(AiProviderException::class);

it('throws a response exception when Gemini returns no candidates', function () {
    Http::fake([
        '*generativelanguage.googleapis.com*' => Http::response(['candidates' => []]),
    ]);

    (new GeminiClient)->generate('Write a blog post');
})->throws(AiResponseException::class);

it('binds AiClientContract to the provider selected by config', function () {
    config(['ai.provider' => 'gemini']);
    expect(app(AiClientContract::class))->toBeInstanceOf(GeminiClient::class);

    config(['ai.provider' => 'anthropic']);
    app()->forgetInstance(AiClientContract::class);
    expect(app()->make(AiClientContract::class))->toBeInstanceOf(AnthropicMessagesClient::class);
});
