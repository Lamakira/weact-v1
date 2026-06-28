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
    // Redirect to the page that owns the payment. The fedapay_transaction_id lives
    // in a different table per payment type — a UGC payment must NOT fall through
    // to the /producer/bookings default (it has no booking):
    //   1. cash mission selection      → mission_payments
    //   2. UGC product-only commission → missions          (paid at publish)
    //   3. UGC hybrid per-Face escrow  → mission_payment_candidatures (paid at acceptance)
    Route::get('/fedapay', function (\Illuminate\Http\Request $request) {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        // FedaPay appends ?id={transaction_id} on redirect
        $transactionId = $request->query('id');
        if ($transactionId) {
            $transactionId = (string) $transactionId;

            // 1. Cash mission selection payment.
            $missionPayment = \App\Models\MissionPayment::where('fedapay_transaction_id', $transactionId)->first();
            if ($missionPayment) {
                return redirect("{$frontendUrl}/producer/missions/{$missionPayment->mission_id}/candidatures?payment=pending");
            }

            // 2. UGC product-only commission paid at publish (stored on the mission itself).
            $mission = \App\Models\Mission::where('fedapay_transaction_id', $transactionId)->first();
            if ($mission) {
                return redirect("{$frontendUrl}/producer/missions/{$mission->id}/candidatures?payment=pending");
            }

            // 3. UGC hybrid per-Face escrow paid at acceptance (parentless candidature entry).
            $escrowEntry = \App\Models\MissionPaymentCandidature::with('candidature')
                ->where('fedapay_transaction_id', $transactionId)
                ->first();
            if ($escrowEntry?->candidature) {
                return redirect("{$frontendUrl}/producer/missions/{$escrowEntry->candidature->mission_id}/candidatures?payment=pending");
            }
        }

        return redirect("{$frontendUrl}/producer/bookings?payment=pending");
    })->name('webhooks.fedapay.return');
});
