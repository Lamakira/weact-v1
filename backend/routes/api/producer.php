<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Producer\BasicInfoController;
use App\Http\Controllers\Api\V1\Producer\CandidatureController;
use App\Http\Controllers\Api\V1\Producer\ConversationController;
use App\Http\Controllers\Api\V1\Producer\FaceController;
use App\Http\Controllers\Api\V1\Producer\MessageController;
use App\Http\Controllers\Api\V1\Producer\MissionController;
use App\Http\Controllers\Api\V1\Producer\ProducerDashboardController;
use App\Http\Controllers\Api\V1\Producer\ProfileController;
use App\Http\Controllers\Api\V1\Producer\RatingController;
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
    // Dashboard routes
    // Note: Controller-level authorization (same pattern as Face) for proper JSON error format
    Route::get('/dashboard/stats', [ProducerDashboardController::class, 'stats']);

    // Basic info routes (agency_name or first_name/last_name based on type)
    Route::get('/basic-info', [BasicInfoController::class, 'show']);
    Route::put('/basic-info', [BasicInfoController::class, 'update']);

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
    Route::post('/missions', [MissionController::class, 'store'])
        ->middleware('verified'); // Email verification required to post missions
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
    Route::post('/candidatures/{candidature}/reject', [CandidatureController::class, 'reject']);

    // Rating routes - rate Face after completed mission (Producer only)
    Route::post('/candidatures/{candidature}/rate', [RatingController::class, 'store'])
        ->middleware(['producer', 'throttle:30,1']);

    // Candidate profile routes
    Route::get('/candidates/{face}', [FaceController::class, 'show']);

    // Conversation routes (Producer messaging)
    // Note: GET list inherits throttle:60,1 from parent group
    // The list endpoint is role-restricted, but show/store use participant-based authorization via policy
    Route::get('/conversations', [ConversationController::class, 'index'])
        ->middleware('producer');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show']);
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:30,1'); // Override: stricter rate limit for message creation
});
