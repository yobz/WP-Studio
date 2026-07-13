<?php

namespace App\Exceptions;

/**
 * For failures of an external dependency the API relies on but doesn't
 * control — a WordPress site's REST API (Milestone 9), an AI provider
 * (future AI milestone), etc. Not used yet (nothing external is called
 * yet), included now so the pattern exists before the first real
 * integration needs it.
 */
class ServiceUnavailableException extends ApiException
{
    public function __construct(string $service, ?string $reason = null)
    {
        $message = "The {$service} service is currently unavailable.";

        parent::__construct(
            message: $reason ? "{$message} {$reason}" : $message,
            errorCode: 'SERVICE_UNAVAILABLE',
            status: 503,
        );
    }
}
