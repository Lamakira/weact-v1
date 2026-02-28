<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookingController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('bookings.store');
});
