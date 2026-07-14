<?php

namespace App\Services\WordPress\Exceptions;

class WordPressResponseException extends WordPressIntegrationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "The WordPress site returned an unexpected response: {$reason}",
            errorCode: 'WORDPRESS_INVALID_RESPONSE',
            status: 502,
        );
    }
}
