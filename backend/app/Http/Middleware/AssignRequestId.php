<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Assigns a unique ID to every request — reused from the client's own
 * `X-Request-Id` header when present (so a request can be traced
 * end-to-end across a future frontend/CDN/proxy), otherwise generated
 * here. Echoed back on the response and pushed into the log context so
 * every log line written during this request can be correlated without
 * manually passing the ID around. See docs/adr/0004-backend-foundation.md
 * ("Observability").
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        $request->attributes->set('request_id', $requestId);
        app('log')->shareContext(['request_id' => $requestId]);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
