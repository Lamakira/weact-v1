<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Retour navigateur FedaPay (GET, non authentifié) après un checkout en même onglet.
 *
 * FedaPay ajoute ?id={transaction_id}&status=… à la callback_url. Ce handler ne fait
 * QUE router : il ne fait jamais confiance au `status` de l'URL et n'expose que des ids.
 * L'état réel est établi par le webhook serveur-à-serveur et par les endpoints de
 * vérification que la page de destination interroge via `?payment_return=<kind>`.
 *
 * Le fedapay_transaction_id vit dans une table différente selon le type de paiement.
 */
class FedapayReturnController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $fallback = "{$frontendUrl}/producer/bookings?payment=pending";

        $transactionId = $request->query('id');
        if (! is_string($transactionId) || $transactionId === '') {
            return redirect($fallback);
        }

        // 1. Sélection de mission cash.
        $missionPayment = MissionPayment::with('mission')->where('fedapay_transaction_id', $transactionId)->first();
        if ($missionPayment?->mission) {
            return redirect("{$frontendUrl}/producer/missions/{$missionPayment->mission->uuid}/candidatures?payment_return=mission_selection");
        }

        // 2. Commission UGC produit-seul payée à la publication (tx stocké sur la mission).
        $mission = Mission::where('fedapay_transaction_id', $transactionId)->first();
        if ($mission) {
            return redirect("{$frontendUrl}/producer/missions?payment_return=mission_commission&mission={$mission->uuid}");
        }

        // 3. Escrow hybride par-Face payé à l'acceptation (entrée de candidature sans parent).
        $escrowEntry = MissionPaymentCandidature::with('candidature.mission')
            ->where('fedapay_transaction_id', $transactionId)
            ->first();
        if ($escrowEntry?->candidature?->mission) {
            $candidature = $escrowEntry->candidature;

            return redirect("{$frontendUrl}/producer/missions/{$candidature->mission->uuid}/candidatures?payment_return=candidature_escrow&candidature={$candidature->uuid}");
        }

        // 4. Booking : le même fedapay_transaction_id sert au paiement cash et à la commission UGC.
        $booking = Booking::where('fedapay_transaction_id', $transactionId)->first();
        if ($booking) {
            $kind = $booking->type_contenu === 'UGC' ? 'booking_commission' : 'booking';

            return redirect("{$frontendUrl}/producer/bookings/{$booking->uuid}?payment_return={$kind}");
        }

        // 5. Abonnement Face.
        if (FaceSubscription::where('provider_reference', $transactionId)->exists()) {
            return redirect("{$frontendUrl}/face/billing?payment_return=subscription");
        }

        return redirect($fallback);
    }
}
