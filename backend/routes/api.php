<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| This file only composes API *versions* — it never defines a route
| directly. Each version's routes live in their own file
| (routes/api_v1.php, and a future routes/api_v2.php) so adding a new
| version never means editing an existing one. See
| docs/adr/0004-backend-foundation.md for the versioning strategy.
|
*/

Route::prefix('v1')->group(base_path('routes/api_v1.php'));
