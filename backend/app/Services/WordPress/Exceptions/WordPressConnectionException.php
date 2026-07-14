<?php

namespace App\Services\WordPress\Exceptions;

class WordPressConnectionException extends WordPressIntegrationException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "Could not connect to the WordPress site: {$reason}",
            errorCode: 'WORDPRESS_UNREACHABLE',
            status: 503,
        );
    }
}
