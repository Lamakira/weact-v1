<?php

declare(strict_types=1);

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
});
