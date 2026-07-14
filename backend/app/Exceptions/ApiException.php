<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public function __construct(
        string $message,
        protected readonly string $errorCode,
        protected readonly int $status = 400,
        protected readonly ?array $details = null,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function details(): ?array
    {
        return $this->details;
    }
}
