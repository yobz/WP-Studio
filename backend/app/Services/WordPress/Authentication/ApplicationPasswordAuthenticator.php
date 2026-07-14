<?php

namespace App\Services\WordPress\Authentication;

use Illuminate\Http\Client\PendingRequest;

class ApplicationPasswordAuthenticator
{
    public function authenticate(PendingRequest $request, string $username, string $applicationPassword): PendingRequest
    {
        return $request->withBasicAuth($username, $applicationPassword);
    }
}
