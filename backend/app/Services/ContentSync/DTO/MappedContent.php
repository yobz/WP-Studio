<?php

namespace App\Services\ContentSync\DTO;

final readonly class MappedContent
{
    public function __construct(
        public int $wordpressId,
        public array $attributes,
        public string $hash,
        public ?int $featuredMediaId = null,
    ) {}
}
