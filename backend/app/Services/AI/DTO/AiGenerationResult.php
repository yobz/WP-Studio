<?php

namespace App\Services\AI\DTO;

class AiGenerationResult
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $inputTokens,
        public readonly ?int $outputTokens,
    ) {}
}
