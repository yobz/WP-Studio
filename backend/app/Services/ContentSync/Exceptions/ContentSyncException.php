<?php

namespace App\Services\ContentSync\Exceptions;

use App\Exceptions\ApiException;

class ContentSyncException extends ApiException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "Could not sync content: {$reason}",
            errorCode: 'CONTENT_SYNC_FAILED',
            status: 422,
        );
    }
}
