<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Http\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->validated())) {
            throw new InvalidCredentialsException;
        }

        $request->session()->regenerate();

        return ApiResponse::success(
            data: new UserResource($request->user()->load('workspaces')),
        );
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(data: null);
    }
}
