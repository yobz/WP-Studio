<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use App\Support\DatabaseHealthChecker;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __construct(
        private readonly DatabaseHealthChecker $databaseHealthChecker,
    ) {}

    public function __invoke(): JsonResponse
    {
        $database = $this->databaseHealthChecker->check();

        $healthy = $database['status'] === 'ok';

        return ApiResponse::success(
            data: [
                'status' => $healthy ? 'ok' : 'degraded',
                'checks' => [
                    'database' => $database,
                ],
            ],
            status: $healthy ? 200 : 503,
        );
    }
}
