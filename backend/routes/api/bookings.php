<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('auth:sanctum')->group(function (): void {
    Route::get('/bookings', [BookingController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('bookings.index');

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

    Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])
        ->middleware('throttle:60,1')
        ->name('bookings.pay');

    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('throttle:60,1')
        ->name('bookings.confirm');

    Route::get('/bookings/{booking}/payment-status', [BookingController::class, 'checkPaymentStatus'])
        ->middleware('throttle:30,1')
        ->name('bookings.payment-status');

    // Wallet (Face-only — 'face' middleware enforces userable_type)
    Route::get('/wallet', [WalletController::class, 'index'])
        ->middleware(['face', 'throttle:60,1'])
        ->name('wallet.index');

    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])
        ->middleware(['face', 'throttle:5,1'])
        ->name('wallet.withdraw');
});
