<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FedapayWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook routes — NO auth:sanctum middleware (uses signature verification only)
Route::prefix('v1/webhooks')->group(function (): void {
    Route::post('/fedapay', [FedapayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.fedapay');

    // FedaPay redirects the user's browser here after checkout (GET).
    // Redirect to the producer bookings page so they can see the payment status.
    Route::get('/fedapay', function () {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        return redirect("{$frontendUrl}/producer/bookings?payment=pending");
    })->name('webhooks.fedapay.return');
});
