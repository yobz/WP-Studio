<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response — an API that will
 * eventually sit behind Sanctum-authenticated, cookie-bearing requests
 * (Milestone 8) should have these from day one rather than retrofitted
 * once there's a session to protect. None of these depend on
 * authentication existing yet.
 */
class SecureHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'browsing-topics=()');

        return $response;
    }
}
