<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\FaceController;
use App\Http\Controllers\Api\V1\Public\FaceReviewController;
use App\Http\Controllers\Api\V1\Public\MissionController;
use App\Http\Controllers\Api\V1\Public\ProducerController;
use App\Http\Controllers\Api\V1\Public\ProducerReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Routes for unauthenticated public access.
| No authentication middleware applied.
|
*/

Route::prefix('v1/public')->middleware('throttle:60,1')->group(function () {
    // Public Faces list (paginated)
    Route::get('/faces', [FaceController::class, 'index']);

    // Public Faces filter options (categories, niches, cities)
    Route::get('/faces/options', [FaceController::class, 'filterOptions']);

    // Public Face profile (limited info)
    Route::get('/faces/{username}', [FaceController::class, 'show']);

    // Producer public profile
    Route::get('/producers/{id}', [ProducerController::class, 'show'])
        ->whereNumber('id');

    // Producer reviews list
    Route::get('/producers/{id}/reviews', [ProducerReviewController::class, 'index'])
        ->whereNumber('id');

    // Face reviews list
    Route::get('/faces/{id}/reviews', [FaceReviewController::class, 'index'])
        ->whereNumber('id');

    // Public Missions list (paginated)
    Route::get('/missions', [MissionController::class, 'index']);

    // Public Mission detail
    Route::get('/missions/{slug}', [MissionController::class, 'show']);
});
