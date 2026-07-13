<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
|
| No auth middleware yet — Milestone 8 adds Laravel Sanctum and will
| wrap the routes below (except /health) in an `auth:sanctum` group.
| See docs/adr/0004-backend-foundation.md and
| docs/adr/0005-domain-model.md.
|
*/

Route::get('health', HealthController::class)->name('health');

Route::get('dashboard/summary', [DashboardController::class, 'summary'])
    ->name('dashboard.summary');

// Real CRUD (Milestone 7) — see SiteController's own doc comment for
// what's still deferred (the WordPress connection flow itself).
Route::apiResource('sites', SiteController::class);

// Real CRUD (Milestone 7).
Route::apiResource('posts', PostController::class);

// Placeholder — a future analytics milestone.
Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

// Placeholder — a future AI integration milestone.
Route::get('ai', [AiController::class, 'index'])->name('ai.index');

// Placeholder — a future settings milestone.
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
