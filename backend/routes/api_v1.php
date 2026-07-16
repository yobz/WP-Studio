<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\UserController;
use App\Http\Controllers\Api\V1\ContentSyncController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SiteController;
use App\Http\Controllers\Api\V1\SystemHealthController;
use App\Http\Middleware\ResolveCurrentWorkspace;
use Illuminate\Support\Facades\Route;
use Nuwave\Lighthouse\Http\GraphQLController;
use Nuwave\Lighthouse\Http\Middleware\AcceptJson;
use Nuwave\Lighthouse\Http\Middleware\AttemptAuthentication;

Route::get('health', HealthController::class)->name('health');

Route::post('login', [AuthController::class, 'login'])
    ->middleware('throttle:login')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('user', [UserController::class, 'show'])->name('user');

    Route::middleware(ResolveCurrentWorkspace::class)->group(function () {
        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->name('dashboard.summary');
        Route::get('dashboard/activity', [DashboardController::class, 'activity'])
            ->name('dashboard.activity');

        Route::apiResource('sites', SiteController::class)->except(['store']);
        Route::post('sites', [SiteController::class, 'store'])
            ->middleware('throttle:wordpress-connection')
            ->name('sites.store');
        Route::post('sites/{site}/disconnect', [SiteController::class, 'disconnect'])
            ->name('sites.disconnect');
        Route::post('sites/{site}/verify', [SiteController::class, 'verifyConnection'])
            ->middleware('throttle:wordpress-connection')
            ->name('sites.verify');
        Route::post('sites/{site}/refresh-metadata', [SiteController::class, 'refreshMetadata'])
            ->middleware('throttle:wordpress-connection')
            ->name('sites.refresh-metadata');
        Route::post('sites/{site}/sync', [ContentSyncController::class, 'sync'])
            ->middleware('throttle:wordpress-connection')
            ->name('sites.sync');
        Route::get('sites/{site}/sync-status', [ContentSyncController::class, 'syncStatus'])
            ->name('sites.sync-status');

        Route::apiResource('posts', PostController::class);

        Route::apiResource('media', MediaController::class)
            ->except(['store'])
            ->parameters(['media' => 'media']);
        Route::post('media', [MediaController::class, 'store'])
            ->middleware('throttle:media-upload')
            ->name('media.store');

        Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('system-health', [SystemHealthController::class, 'index'])
            ->name('system-health.index');

        Route::get('ai', [AiController::class, 'index'])->name('ai.index');

        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');

        Route::post('graphql', GraphQLController::class)
            ->middleware([AcceptJson::class, AttemptAuthentication::class])
            ->name('graphql');
    });
});
