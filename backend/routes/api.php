<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\RegisterFaceController;
use App\Http\Controllers\Api\V1\Auth\RegisterProducerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group.
|
*/

Route::prefix('v1')->group(function (): void {
    // Health check endpoint
    Route::get('/health', fn() => response()->json([
        'data' => ['status' => 'ok'],
        'meta' => ['timestamp' => now()->toIso8601String()],
        'message' => 'API is running'
    ]));

    // Authentication routes (public)
    Route::prefix('auth')->group(function (): void {
        Route::post('/register/face', RegisterFaceController::class)
            ->middleware('throttle:5,1')
            ->name('auth.register.face');

        Route::post('/register/producer', RegisterProducerController::class)
            ->middleware('throttle:5,1')
            ->name('auth.register.producer');
    });

    // Protected routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/user', fn() => response()->json([
            'data' => request()->user(),
            'meta' => [],
            'message' => 'Authenticated user retrieved'
        ]));
    });
});

