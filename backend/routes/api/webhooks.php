<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FedapayWebhookController;
use App\Http\Controllers\Api\V1\Webhooks\FedapayReturnController;
use App\Http\Controllers\Api\V1\Webhooks\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

// Webhook routes — NO auth:sanctum middleware (uses signature verification only)
Route::prefix('v1/webhooks')->group(function (): void {
    Route::post('/fedapay', [FedapayWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.fedapay');

    // FedaPay redirects the user's browser here after checkout (GET).
    // Routes to the page that initiated the payment with ?payment_return=<kind>
    // (see FedapayReturnController) — state itself stays owned by the webhook.
    // Unauthenticated and up to 5 lookups per hit: throttled.
    Route::get('/fedapay', FedapayReturnController::class)
        ->middleware('throttle:30,1')
        ->name('webhooks.fedapay.return');

    // WhatsApp Cloud API (Meta) : vérification d'abonnement (GET) puis statuts / messages entrants (POST,
    // signature X-Hub-Signature-256).
    Route::get('/whatsapp', [WhatsappWebhookController::class, 'verify'])
        ->middleware('throttle:60,1')
        ->name('webhooks.whatsapp.verify');

    Route::post('/whatsapp', [WhatsappWebhookController::class, 'handle'])
        ->middleware('throttle:120,1')
        ->name('webhooks.whatsapp');
});
