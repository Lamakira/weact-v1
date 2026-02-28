<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('bookings.store');

    Route::get('/bookings/{booking}', [BookingController::class, 'show'])
        ->name('bookings.show');

    Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept'])
        ->middleware('throttle:60,1')
        ->name('bookings.accept');

    Route::post('/bookings/{booking}/refuse', [BookingController::class, 'refuse'])
        ->middleware('throttle:60,1')
        ->name('bookings.refuse');
});
