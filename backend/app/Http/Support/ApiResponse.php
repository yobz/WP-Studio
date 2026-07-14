<?php

namespace App\Http\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiResponse
{
    public static function success(
        mixed $data,
        ?array $meta = null,
        int $status = 200,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(
        string $code,
        string $message,
        int $status = 400,
        ?array $details = null,
        ?Request $request = null,
    ): JsonResponse {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        $requestId = $request?->attributes->get('request_id');
        if ($requestId !== null) {
            $payload['request_id'] = $requestId;
        }

        return response()->json($payload, $status);
    }
}
