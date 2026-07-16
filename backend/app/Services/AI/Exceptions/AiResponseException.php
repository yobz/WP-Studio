<?php

namespace App\Services\AI\Exceptions;

class AiResponseException extends AiIntegrationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "The AI service returned an unusable response: {$reason}",
            errorCode: 'AI_RESPONSE_INVALID',
            status: 502,
        );
    }
}
