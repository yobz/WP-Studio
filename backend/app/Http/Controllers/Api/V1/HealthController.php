<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deliberately separate from Laravel's built-in `/up` health check
 * (bootstrap/app.php's `health: '/up'`) — `/up` is for Laravel's own
 * maintenance-mode-aware infrastructure probe (used by e.g. load
 * balancers), unauthenticated and outside `/api`. This one lives under
 * `/api/v1` so a frontend or uptime monitor hitting the API's own base
 * path gets a real answer, and it checks the database connection
 * specifically (the one dependency every endpoint in this milestone
 * needs), not just "the PHP process is alive."
 */
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
            // Deliberately generic — a health check response is
            // sometimes externally visible (uptime monitors, status
            // pages); it should say "the database is unreachable," not
            // repeat driver/credential details from the exception.
            return ['status' => 'error', 'message' => 'Database connection failed.'];
        }
    }
}
