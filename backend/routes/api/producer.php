<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Producer\BasicInfoController;
use App\Http\Controllers\Api\V1\Producer\CandidatureController;
use App\Http\Controllers\Api\V1\Producer\ConversationController;
use App\Http\Controllers\Api\V1\Producer\FaceController;
use App\Http\Controllers\Api\V1\Producer\MessageController;
use App\Http\Controllers\Api\V1\Producer\MissionController;
use App\Http\Controllers\Api\V1\Producer\MissionPaymentController;
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

Route::prefix('v1/producer')->middleware(['auth:sanctum', 'api.token'])->group(function () {
    // Dashboard routes
    // Note: Controller-level authorization (same pattern as Face) for proper JSON error format
    Route::get('/dashboard/stats', [ProducerDashboardController::class, 'stats'])
        ->middleware('throttle:ui-read');

    // Basic info routes (agency_name or first_name/last_name based on type)
    Route::get('/basic-info', [BasicInfoController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/basic-info', [BasicInfoController::class, 'update'])
        ->middleware('throttle:60,1');

    // Profile routes
    Route::get('/profile', [ProfileController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:60,1');
    Route::delete('/profile/photo', [ProfileController::class, 'deletePhoto'])
        ->middleware('throttle:60,1');

    // Bio routes
    Route::get('/profile/bio', [ProfileController::class, 'showBio'])
        ->middleware('throttle:ui-read');
    Route::put('/profile/bio', [ProfileController::class, 'updateBio'])
        ->middleware('throttle:60,1');

    // Agency logo routes
    Route::get('/profile/logo', [ProfileController::class, 'showLogo'])
        ->middleware('throttle:ui-read');
    Route::post('/profile/logo', [ProfileController::class, 'updateLogo'])
        ->middleware('throttle:60,1');
    Route::delete('/profile/logo', [ProfileController::class, 'deleteLogo'])
        ->middleware('throttle:60,1');

    // Mission routes
    Route::get('/missions', [MissionController::class, 'index'])
        ->middleware('throttle:ui-read');
    Route::post('/missions', [MissionController::class, 'store'])
        ->middleware(['verified', 'throttle:60,1']); // Email verification required to post missions
    Route::get('/missions/{mission}', [MissionController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::put('/missions/{mission}', [MissionController::class, 'update'])
        ->middleware('throttle:60,1');
    Route::delete('/missions/{mission}', [MissionController::class, 'destroy'])
        ->middleware('throttle:60,1');
    Route::post('/missions/{mission}/close', [MissionController::class, 'close'])
        ->middleware('throttle:60,1');
    Route::post('/missions/{mission}/reopen', [MissionController::class, 'reopen'])
        ->middleware('throttle:60,1');
    Route::post('/missions/{mission}/complete', [MissionController::class, 'complete'])
        ->middleware('throttle:60,1');

    // Mission payment routes
    Route::post('/missions/{mission}/confirm-selection', [MissionPaymentController::class, 'confirmAndPay'])
        ->middleware('throttle:10,1');
    Route::get('/missions/{mission}/payment-status', [MissionPaymentController::class, 'paymentStatus'])
        ->middleware('throttle:polling');

    // Candidature routes (nested under missions)
    Route::get('/missions/{mission}/candidatures', [CandidatureController::class, 'index'])
        ->middleware('throttle:ui-read');

    // Candidature action routes
    Route::post('/candidatures/{candidature}/accept', [CandidatureController::class, 'accept'])
        ->middleware('throttle:60,1');
    Route::post('/candidatures/{candidature}/reject', [CandidatureController::class, 'reject'])
        ->middleware('throttle:60,1');

    // Rating routes - rate Face after completed mission (Producer only)
    Route::post('/candidatures/{candidature}/rate', [RatingController::class, 'store'])
        ->middleware(['producer', 'throttle:30,1']);

    // Candidate profile routes
    Route::get('/candidates/{face}', [FaceController::class, 'show'])
        ->middleware('throttle:ui-read');

    // Conversation routes (Producer messaging)
    // GET routes use the dedicated UI read limiter.
    // The list endpoint is role-restricted, but show/store use participant-based authorization via policy.
    Route::get('/conversations', [ConversationController::class, 'index'])
        ->middleware('throttle:ui-read')
        ->middleware('producer');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
        ->middleware('throttle:ui-read');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:30,1'); // Override: stricter rate limit for message creation
});
