<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Support\DatabaseHealthChecker;
use App\Support\QueueHealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly DatabaseHealthChecker $databaseHealthChecker,
        private readonly QueueHealthChecker $queueHealthChecker,
    ) {}

    public function __invoke(): JsonResponse
    {
        $database = $this->databaseHealthChecker->check();
        $queue = $this->queueHealthChecker->check();

        $healthy = $database['status'] === 'ok' && $queue['failed'] === 0;

        return ApiResponse::success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $database,
                    'queue' => $queue,
                ],
            ],
            status: $healthy ? 200 : 503,
        );
    }
}
