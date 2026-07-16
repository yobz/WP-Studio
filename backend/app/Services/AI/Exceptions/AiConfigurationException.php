<?php

namespace App\Services\AI\Exceptions;

class AiConfigurationException extends AiIntegrationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: 'AI generation is temporarily unavailable. Please try again later.',
            errorCode: 'AI_CONFIGURATION_ERROR',
            status: 500,
            details: config('app.debug') ? ['reason' => $reason] : null,
        );
    }
}
