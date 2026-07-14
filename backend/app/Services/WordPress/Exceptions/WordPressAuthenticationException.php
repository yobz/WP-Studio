<?php

namespace App\Services\WordPress\Exceptions;

class WordPressAuthenticationException extends WordPressIntegrationException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The WordPress site rejected the supplied credentials.',
            errorCode: 'WORDPRESS_AUTHENTICATION_FAILED',
            status: 422,
        );
    }
}
