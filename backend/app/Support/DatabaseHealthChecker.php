<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseHealthChecker
{
    public function check(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'error', 'message' => 'Database connection failed.'];
        }
    }
}
