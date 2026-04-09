<?php

declare(strict_types=1);

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\V1\Public\ArticleController;
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
    Route::get('/producers/{producer:slug}', [ProducerController::class, 'show']);

    // Producer reviews list
    Route::get('/producers/{producer:slug}/reviews', [ProducerReviewController::class, 'index']);

    // Face reviews list
    Route::get('/faces/{face:username}/reviews', [FaceReviewController::class, 'index']);

    // Public Articles list (paginated)
    Route::get('/articles', [ArticleController::class, 'index']);

    // Public Article detail
    Route::get('/articles/{slug}', [ArticleController::class, 'show']);

    // Public Missions list (paginated)
    Route::get('/missions', [MissionController::class, 'index']);

    // Public Mission detail
    Route::get('/missions/{slug}', [MissionController::class, 'show']);

    // Contact form
    Route::post('/contact', [ContactController::class, 'store'])
        ->middleware('throttle:5,1');
});
