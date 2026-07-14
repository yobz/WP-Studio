<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class QueueHealthChecker
{
    public function check(): array
    {
        $driver = (string) config('queue.default');
        $failed = (int) DB::table('failed_jobs')->count();

        if ($driver !== 'database') {
            return [
                'driver' => $driver,
                'pending' => null,
                'failed' => $failed,
                'oldest_pending_seconds' => null,
            ];
        }

        $pending = (int) DB::table('jobs')->count();
        $oldestAvailableAt = DB::table('jobs')->min('available_at');

        return [
            'driver' => $driver,
            'pending' => $pending,
            'failed' => $failed,
            'oldest_pending_seconds' => $oldestAvailableAt !== null
                ? max(0, now()->timestamp - (int) $oldestAvailableAt)
                : null,
        ];
    }
}
