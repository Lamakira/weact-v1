<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FedapayWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\FedapayReturnController;
use Illuminate\Support\Facades\Route;

// Webhook routes — NO auth:sanctum middleware (uses signature verification only)
Route::prefix('v1/webhooks')->group(function (): void {
    Route::post('/fedapay', [FedapayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.fedapay');

    // FedaPay redirects the user's browser here after checkout (GET).
    // Routes to the page that initiated the payment with ?payment_return=<kind>
    // (see FedapayReturnController) — state itself stays owned by the webhook.
    Route::get('/fedapay', FedapayReturnController::class)->name('webhooks.fedapay.return');
});
