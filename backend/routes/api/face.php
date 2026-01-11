<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Face\AlbumController;
use App\Http\Controllers\Api\V1\Face\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Face API Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated Face users to manage their profile.
|
*/

Route::prefix('v1/face')->middleware(['auth:sanctum'])->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

    // Album routes
    Route::get('/album', [AlbumController::class, 'index']);
    Route::post('/album', [AlbumController::class, 'store'])
        ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute
    Route::delete('/album/{photo}', [AlbumController::class, 'destroy']);
    Route::put('/album/reorder', [AlbumController::class, 'reorder']);
});
