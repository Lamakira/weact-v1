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
    // Redirect to the appropriate page based on payment type.
    Route::get('/fedapay', function (\Illuminate\Http\Request $request) {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        // FedaPay appends ?id={transaction_id} on redirect
        $transactionId = $request->query('id');
        if ($transactionId) {
            $missionPayment = \App\Models\MissionPayment::where('fedapay_transaction_id', (string) $transactionId)->first();
            if ($missionPayment) {
                return redirect("{$frontendUrl}/producer/missions/{$missionPayment->mission_id}/candidatures?payment=pending");
            }
        }

        return redirect("{$frontendUrl}/producer/bookings?payment=pending");
    })->name('webhooks.fedapay.return');
});
