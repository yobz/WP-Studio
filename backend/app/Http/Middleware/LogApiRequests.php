<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * One structured log line per API request — method, path, status, and
 * duration. Runs after AssignRequestId (registered ahead of this in the
 * `api` middleware group), so every line already carries the shared
 * request_id context automatically; this is the request-level access
 * log that context enables, not a duplicate of it.
 */
class LogApiRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        Log::info('api_request', [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status' => $response->getStatusCode(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $response;
    }
}
