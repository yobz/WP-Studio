<?php

namespace App\Providers;

use App\Services\AI\Client\AnthropicMessagesClient;
use App\Services\AI\Client\GeminiClient;
use App\Services\AI\Contracts\AiClientContract;
use App\Services\WordPress\Client\HttpWordPressClient;
use App\Services\WordPress\Contracts\WordPressClientContract;
use App\Support\CurrentWorkspaceContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentWorkspaceContext::class);

        $this->app->bind(WordPressClientContract::class, HttpWordPressClient::class);

        $this->app->bind(AiClientContract::class, function (): AiClientContract {
            return match (config('ai.provider')) {
                'gemini' => $this->app->make(GeminiClient::class),
                default => $this->app->make(AnthropicMessagesClient::class),
            };
        });
    }

    public function boot(): void
    {
        // A generous backstop applied to every API request (see
        // bootstrap/app.php's `throttle:api`) — the four limiters below
        // stay in place as tighter, endpoint-specific limits stacked on
        // top of this one, not replaced by it.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('wordpress-connection', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id);
        });

        RateLimiter::for('media-upload', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->user()?->id);
        });

        RateLimiter::for('ai-generation', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id);
        });
    }
}
