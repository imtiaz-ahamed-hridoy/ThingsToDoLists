<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes are organized under versioned route files.
| - v1/public.php  → Public (no auth) routes
| - v1/auth.php    → Auth & authenticated user routes
|
| Public media is served directly via the public/storage symlink.
| Use Storage::disk('public')->url($path) or model accessors to generate URLs.
| with NO middleware groups to avoid Sanctum/session interference for public users.
|
*/

Route::group(['prefix' => 'v1', 'middleware' => 'api'], function () {
    require base_path('routes/api/v1/public.php');
    require base_path('routes/api/v1/auth.php');
});
