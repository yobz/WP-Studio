<?php

namespace App\Services\AI\Exceptions;

class AiProviderException extends AiIntegrationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "The AI service is temporarily unavailable: {$reason}",
            errorCode: 'AI_PROVIDER_UNAVAILABLE',
            status: 503,
        );
    }
}
