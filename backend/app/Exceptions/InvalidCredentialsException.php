<?php

namespace App\Exceptions;

class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            message: 'These credentials do not match our records.',
            errorCode: 'INVALID_CREDENTIALS',
            status: 401,
        );
    }
}
