<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder for a future Settings milestone. Real settings are
 * inherently per-user (Milestone 8 adds auth) — nothing meaningful to
 * persist or return until there's a user to scope settings to.
 */
class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: [
            'message' => 'Settings are not yet implemented.',
        ]);
    }
}
