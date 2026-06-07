<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingMessageController;
use App\Http\Controllers\Api\V1\BookingRatingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'api.token'])->group(function (): void {
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

    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->middleware('throttle:60,1')
        ->name('bookings.cancel');

    Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay'])
        ->middleware('throttle:60,1')
        ->name('bookings.pay');

    // UGC commission payment (dedicated path — no escrow, charges commission only)
    Route::post('/bookings/{booking}/pay-commission', [BookingController::class, 'payCommission'])
        ->middleware('throttle:60,1')
        ->name('bookings.pay-commission');

    Route::get('/bookings/{booking}/commission-status', [BookingController::class, 'commissionStatus'])
        ->middleware('throttle:polling')
        ->name('bookings.commission-status');

    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm'])
        ->middleware('throttle:60,1')
        ->name('bookings.confirm');

    Route::post('/bookings/{booking}/report-no-show', [BookingController::class, 'reportNoShow'])
        ->middleware('throttle:60,1')
        ->name('bookings.report-no-show');

    Route::post('/bookings/{booking}/rate', [BookingRatingController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('bookings.rate');

    Route::get('/bookings/{booking}/payment-status', [BookingController::class, 'checkPaymentStatus'])
        ->middleware('throttle:polling')
        ->name('bookings.payment-status');

    // Booking Chat (b4-1)
    Route::get('/bookings/{booking}/messages', [BookingMessageController::class, 'index'])
        ->middleware('throttle:polling')
        ->name('bookings.messages.index');

    Route::post('/bookings/{booking}/messages', [BookingMessageController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('bookings.messages.store');

    // Shared notifications (Face + Producer — auth:sanctum only, b6-2)
    Route::get('/me/notifications', [NotificationController::class, 'index'])
        ->middleware('throttle:60,1')
        ->name('me.notifications.index');

    Route::get('/me/notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->middleware('throttle:polling')
        ->name('me.notifications.unread-count');

    Route::post('/me/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
        ->middleware('throttle:60,1')
        ->name('me.notifications.mark-read');

    Route::post('/me/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
        ->middleware('throttle:30,1')
        ->name('me.notifications.mark-all-read');

    // Wallet (Face + Producer read access)
    Route::get('/wallet', [WalletController::class, 'index'])
        ->middleware(['face_or_producer', 'throttle:60,1'])
        ->name('wallet.index');

    Route::post('/wallet/withdraw', [WalletController::class, 'withdraw'])
        ->middleware(['face_or_producer', 'throttle:withdrawals'])
        ->name('wallet.withdraw');
});
