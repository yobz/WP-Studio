<?php

namespace App\Services\Media\DTO;

final readonly class StoredFile
{
    public function __construct(
        public string $disk,
        public string $storagePath,
    ) {}
}
