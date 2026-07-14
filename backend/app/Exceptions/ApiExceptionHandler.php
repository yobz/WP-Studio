<?php

namespace App\Exceptions;

use App\Http\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! self::isApiRequest($request)) {
                return null;
            }

            return self::render($e, $request);
        });
    }

    private static function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private static function render(Throwable $e, Request $request)
    {
        return match (true) {
            $e instanceof ValidationException => ApiResponse::error(
                code: 'VALIDATION_FAILED',
                message: 'The given data was invalid.',
                status: 422,
                details: $e->errors(),
                request: $request,
            ),
            $e instanceof AuthenticationException => ApiResponse::error(
                code: 'UNAUTHENTICATED',
                message: 'Authentication is required to access this resource.',
                status: 401,
                request: $request,
            ),
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => ApiResponse::error(
                code: 'FORBIDDEN',
                message: 'You are not authorized to perform this action.',
                status: 403,
                request: $request,
            ),
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => ApiResponse::error(
                code: 'NOT_FOUND',
                message: 'The requested resource could not be found.',
                status: 404,
                request: $request,
            ),
            $e instanceof TooManyRequestsHttpException => ApiResponse::error(
                code: 'RATE_LIMITED',
                message: 'Too many requests. Please try again later.',
                status: 429,
                request: $request,
            ),
            $e instanceof ApiException => ApiResponse::error(
                code: $e->errorCode(),
                message: $e->getMessage(),
                status: $e->status(),
                details: $e->details(),
                request: $request,
            ),
            $e instanceof HttpExceptionInterface => ApiResponse::error(
                code: 'HTTP_ERROR',
                message: $e->getMessage() ?: 'An error occurred while processing the request.',
                status: $e->getStatusCode(),
                request: $request,
            ),
            default => ApiResponse::error(
                code: 'INTERNAL_ERROR',
                message: config('app.debug')
                    ? $e->getMessage()
                    : 'An unexpected error occurred. Please try again later.',
                status: 500,
                request: $request,
            ),
        };
    }
}
