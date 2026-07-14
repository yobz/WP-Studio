<?php

namespace App\Providers;

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
    }

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        RateLimiter::for('wordpress-connection', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->user()?->id);
        });
    }
}
