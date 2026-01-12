<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Face\ActingVideoController;
use App\Http\Controllers\Api\V1\Face\AlbumController;
use App\Http\Controllers\Api\V1\Face\BioLocationController;
use App\Http\Controllers\Api\V1\Face\PhysicalCharacteristicsController;
use App\Http\Controllers\Api\V1\Face\PresentationVideoController;
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

    // Presentation video routes
    Route::get('/presentation-video', [PresentationVideoController::class, 'show']);
    Route::post('/presentation-video', [PresentationVideoController::class, 'store'])
        ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute
    Route::delete('/presentation-video', [PresentationVideoController::class, 'destroy']);

    // Acting video routes
    Route::get('/acting-video', [ActingVideoController::class, 'show']);
    Route::post('/acting-video', [ActingVideoController::class, 'store'])
        ->middleware('throttle:10,1'); // Rate limit: 10 requests per minute
    Route::delete('/acting-video', [ActingVideoController::class, 'destroy']);

    // Bio and location routes
    Route::get('/bio-location', [BioLocationController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::put('/bio-location', [BioLocationController::class, 'update'])
        ->middleware('throttle:60,1');

    // Physical characteristics routes
    Route::get('/physical-characteristics', [PhysicalCharacteristicsController::class, 'show'])
        ->middleware('throttle:60,1');
    Route::put('/physical-characteristics', [PhysicalCharacteristicsController::class, 'update'])
        ->middleware('throttle:60,1');
});
