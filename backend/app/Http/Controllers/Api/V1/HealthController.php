<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->checkDatabase();

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

    /**
     * @return array{status: string, message?: string}
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'error', 'message' => 'Database connection failed.'];
        }
    }
}
