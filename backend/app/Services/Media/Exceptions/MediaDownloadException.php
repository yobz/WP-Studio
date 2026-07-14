<?php

namespace App\Services\Media\Exceptions;

use App\Exceptions\ApiException;

class MediaDownloadException extends ApiException
{
    public function __construct(string $reason)
    {
        parent::__construct(
            message: "Could not download media: {$reason}",
            errorCode: 'MEDIA_DOWNLOAD_FAILED',
            status: 422,
        );
    }
}
