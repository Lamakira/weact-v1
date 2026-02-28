<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FedapayWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook routes — NO auth:sanctum middleware (uses signature verification only)
Route::prefix('v1/webhooks')->group(function (): void {
    Route::post('/fedapay', [FedapayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.fedapay');
});
