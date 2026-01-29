<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Public\FaceReviewController;
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
    // Producer public profile
    Route::get('/producers/{id}', [ProducerController::class, 'show'])
        ->whereNumber('id');

    // Producer reviews list
    Route::get('/producers/{id}/reviews', [ProducerReviewController::class, 'index'])
        ->whereNumber('id');

    // Face reviews list
    Route::get('/faces/{id}/reviews', [FaceReviewController::class, 'index'])
        ->whereNumber('id');
});
