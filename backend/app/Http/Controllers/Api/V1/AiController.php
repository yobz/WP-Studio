<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Placeholder for a future AI integration milestone. The frontend's
 * AI Assistant Preview widget already documents its intended
 * integration point (`POST /api/ai/drafts`, see
 * src/features/dashboard/components/ai-assistant-preview.tsx) — this
 * `GET` placeholder exists only to prove the route/versioning
 * structure now; the real generation endpoint, its request contract,
 * and provider integration are that future milestone's work.
 */
class AiController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: [
            'message' => 'AI integration is not yet implemented.',
        ]);
    }
}
