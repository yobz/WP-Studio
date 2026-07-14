<?php

namespace App\Exceptions;

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
