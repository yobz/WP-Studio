<?php

/**
 * Error monitoring only — not performance tracing/APM
 * (traces_sample_rate stays 0). Every key not listed here falls back to
 * the package's own default (vendor/sentry/sentry-laravel/config/sentry.php),
 * merged automatically via the SDK's mergeConfigFrom(). No DSN is
 * configured in this repo; SENTRY_LARAVEL_DSN unset means the SDK is a
 * safe no-op — nothing is sent anywhere. See
 * docs/adr/0016-observability.md.
 */
return [

    'dsn' => env('SENTRY_LARAVEL_DSN'),

    'environment' => env('APP_ENV'),

    // Never send PII (emails, IPs, request bodies) to a third party by default.
    'send_default_pii' => false,

    // Capture every reported error; no performance tracing/APM sampling.
    'sample_rate' => 1.0,
    'traces_sample_rate' => 0.0,

    'ignore_transactions' => [
        '/up',
        '/api/v1/health',
    ],

];
