<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;

/**
 * Swaps a channel's line formatter for Monolog's JsonFormatter — every
 * field already flowing through the logger (message, level, context,
 * including AssignRequestId's shared request_id) comes out as one JSON
 * object per line instead of a human-oriented string, so log
 * aggregation tooling (CloudWatch, Datadog, ELK) can parse it without a
 * custom grok pattern. Opt-in via LOG_JSON so local `tail -f` output
 * stays human-readable by default.
 */
class JsonFormatterTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getLogger()->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter);
        }
    }
}
