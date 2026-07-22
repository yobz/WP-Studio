<?php

use App\Exceptions\ApiExceptionHandler;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\LogApiRequests;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration as SentryIntegration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->api(prepend: [
            AssignRequestId::class,
        ]);

        // A generous global backstop (see AppServiceProvider's 'api'
        // limiter) — every API request gets one, not just the four
        // specific endpoints with their own tighter limits.
        $middleware->throttleApi();

        $middleware->api(append: [
            LogApiRequests::class,
        ]);

        $middleware->append(SecureHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        SentryIntegration::handles($exceptions);
        ApiExceptionHandler::register($exceptions);
    })->create();
