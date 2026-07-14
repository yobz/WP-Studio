<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnalyticsController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: [
            'message' => 'Analytics integration is not yet implemented.',
        ]);
    }
}
