<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Producer\CandidatureController;
use App\Http\Controllers\Api\V1\Producer\FaceController;
use App\Http\Controllers\Api\V1\Producer\MissionController;
use App\Http\Controllers\Api\V1\Producer\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Producer API Routes
|--------------------------------------------------------------------------
|
| Routes for authenticated Producer users to manage their profile.
|
*/

Route::prefix('v1/producer')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto']);

    // Bio routes
    Route::get('/profile/bio', [ProfileController::class, 'showBio']);
    Route::put('/profile/bio', [ProfileController::class, 'updateBio']);

    // Agency logo routes
    Route::get('/profile/logo', [ProfileController::class, 'showLogo']);
    Route::post('/profile/logo', [ProfileController::class, 'updateLogo']);
    Route::delete('/profile/logo', [ProfileController::class, 'deleteLogo']);

    // Mission routes
    Route::get('/missions', [MissionController::class, 'index']);
    Route::post('/missions', [MissionController::class, 'store']);
    Route::get('/missions/{mission}', [MissionController::class, 'show']);
    Route::put('/missions/{mission}', [MissionController::class, 'update']);
    Route::delete('/missions/{mission}', [MissionController::class, 'destroy']);
    Route::post('/missions/{mission}/close', [MissionController::class, 'close']);
    Route::post('/missions/{mission}/reopen', [MissionController::class, 'reopen']);
    Route::post('/missions/{mission}/complete', [MissionController::class, 'complete']);

    // Candidature routes (nested under missions)
    Route::get('/missions/{mission}/candidatures', [CandidatureController::class, 'index']);

    // Candidature action routes
    Route::post('/candidatures/{candidature}/accept', [CandidatureController::class, 'accept']);

    // Candidate profile routes
    Route::get('/candidates/{face}', [FaceController::class, 'show']);
});
