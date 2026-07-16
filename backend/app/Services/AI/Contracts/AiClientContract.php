<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTO\AiGenerationResult;

interface AiClientContract
{
    public function generate(string $prompt): AiGenerationResult;
}
