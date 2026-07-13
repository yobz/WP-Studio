<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Restricted to the Next.js frontend's origin specifically — the
    | framework default (`allowed_origins => ['*']`) is deliberately
    | not used here; a wildcard origin combined with credentialed
    | requests is a real security foundation gap, not just a style
    | choice. See docs/adr/0004-backend-foundation.md ("Security").
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter(explode(',', env('FRONTEND_URLS', 'http://localhost:3000'))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'Authorization', 'X-Requested-With', 'X-Request-Id'],

    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    // `true` in anticipation of Milestone 8's Sanctum SPA (cookie-
    // based) authentication, which requires credentialed cross-origin
    // requests. Inert until then — no endpoint reads a session/cookie
    // yet, so this has no effect today beyond the header it sends.
    'supports_credentials' => true,

];
