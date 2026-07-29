<?php

/*
|--------------------------------------------------------------------------
| API routes — all prefixed with /api automatically by Laravel
|--------------------------------------------------------------------------
|
| These routes return JSON. They use Sanctum token authentication rather
| than session cookies, which is the standard for API clients.
|
| auth:sanctum means a valid Bearer token must be present in the
| Authorization header. Unauthenticated requests get a 401 JSON response.
|
*/

use App\Http\Controllers\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public — no auth required
Route::get('/posts', [PostController::class, 'apiIndex']);
Route::get('/posts/{post}', [PostController::class, 'apiShow']);

// Protected — valid Sanctum token required
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts', [PostController::class, 'apiStore']);
    Route::patch('/posts/{post}/publish', [PostController::class, 'apiPublish']);
    Route::delete('/posts/{post}', [PostController::class, 'apiDestroy']);
});
