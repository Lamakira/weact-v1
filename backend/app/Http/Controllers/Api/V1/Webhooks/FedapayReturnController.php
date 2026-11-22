<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\FaceSubscription;
use App\Models\Mission;
use App\Models\MissionPayment;
use App\Models\MissionPaymentCandidature;
use App\Services\FedapayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Retour navigateur FedaPay (GET, non authentifié) après un checkout en même onglet.
 *
 * FedaPay ajoute ?id={transaction_id}&status=… à la callback_url. Ce handler ne fait
 * QUE router : il ne fait jamais confiance au `status` de l'URL et n'expose que des ids.
 * L'état réel est établi par le webhook serveur-à-serveur et par les endpoints de
 * vérification que la page de destination interroge via `?payment_return=<kind>`.
 *
 * Un indice `fedapay_reason` (ex. solde insuffisant) peut s'ajouter après un échec : il
 * est lu chez FedaPay (jamais dans l'URL), uniquement pour NOS transactions (la route
 * n'est pas authentifiée : elle ne doit pas permettre de déclencher des appels FedaPay
 * arbitraires), sert à l'affichage seul et n'est jamais une source d'état.
 *
 * Le fedapay_transaction_id vit dans une table différente selon le type de paiement.
 */
class FedapayReturnController extends Controller
{
    private const STATUS_HINTS = ['approved', 'canceled', 'declined'];

    private const FAILURE_REASONS = ['INSUFFICIENT_FUND_ERROR' => 'insufficient_funds'];

    public function __invoke(Request $request, FedapayService $fedapay): RedirectResponse
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $hint = $this->statusHint($request);
        // No entity found (unknown id, or the webhook already cleaned it up — e.g. a
        // declined hybrid escrow entry is deleted before the browser arrives): neutral
        // authenticated SPA page that only receives the whitelisted display hint.
        $fallback = $this->withHint("{$frontendUrl}/paiement/retour", $hint);

        $transactionId = $request->query('id');
        if (! is_string($transactionId) || $transactionId === '') {
            return redirect($fallback);
        }

        $destination = $this->resolveDestination($frontendUrl, $transactionId);
        if ($destination === null) {
            return redirect($fallback);
        }

        $url = $this->withHint($destination, $hint);
        $reason = $this->failureReason($fedapay, $transactionId, $hint);
        if ($reason !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?').'fedapay_reason='.$reason;
        }

        return redirect($url);
    }

    /**
     * Destination (sans indice) de la transaction, ou null si aucune entité ne la porte.
     */
    private function resolveDestination(string $frontendUrl, string $transactionId): ?string
    {
        // 1. Sélection de mission cash.
        $missionPayment = MissionPayment::with('mission')->where('fedapay_transaction_id', $transactionId)->first();
        if ($missionPayment?->mission) {
            return "{$frontendUrl}/producer/missions/{$missionPayment->mission->uuid}/candidatures?payment_return=mission_selection";
        }

        // 2. Commission UGC produit-seul payée à la publication (tx stocké sur la mission).
        $mission = Mission::where('fedapay_transaction_id', $transactionId)->first();
        if ($mission) {
            return "{$frontendUrl}/producer/missions?payment_return=mission_commission&mission={$mission->uuid}";
        }

        // 3. Escrow hybride par-Face payé à l'acceptation (entrée de candidature sans parent).
        $escrowEntry = MissionPaymentCandidature::with('candidature.mission')
            ->where('fedapay_transaction_id', $transactionId)
            ->first();
        if ($escrowEntry?->candidature?->mission) {
            $candidature = $escrowEntry->candidature;

            return "{$frontendUrl}/producer/missions/{$candidature->mission->uuid}/candidatures?payment_return=candidature_escrow&candidature={$candidature->uuid}";
        }

        // 4. Booking : le même fedapay_transaction_id sert au paiement cash et à la commission UGC.
        $booking = Booking::where('fedapay_transaction_id', $transactionId)->first();
        if ($booking) {
            $kind = $booking->type_contenu === 'UGC' ? 'booking_commission' : 'booking';

            return "{$frontendUrl}/producer/bookings/{$booking->uuid}?payment_return={$kind}";
        }

        // 5. Abonnement Face.
        if (FaceSubscription::where('provider_reference', $transactionId)->exists()) {
            return "{$frontendUrl}/face/billing?payment_return=subscription";
        }

        return null;
    }

    /**
     * Raison d'échec affichable, lue chez FedaPay (jamais dans l'URL). Appelée seulement
     * pour NOTRE transaction (entité trouvée) et un indice canceled/declined.
     */
    private function failureReason(FedapayService $fedapay, string $transactionId, ?string $hint): ?string
    {
        if (! in_array($hint, ['canceled', 'declined'], true) || ! ctype_digit($transactionId)) {
            return null;
        }

        try {
            $transaction = $fedapay->retrieveTransaction((int) $transactionId);
        } catch (\Throwable $e) {
            Log::warning('FedaPay return: could not retrieve transaction for failure reason', [
                'transaction_id' => $transactionId,
                'exception' => $e::class,
            ]);

            return null;
        }

        // FedaPay garde un last_error_code périmé sur les transactions réussies : on
        // vérifie donc le statut récupéré avant de se fier au code.
        if (! in_array($transaction->status ?? null, ['canceled', 'declined'], true)) {
            return null;
        }

        $code = $transaction->last_error_code ?? null;

        return is_string($code) ? (self::FAILURE_REASONS[$code] ?? null) : null;
    }

    /**
     * FedaPay appends status=approved|canceled|declined. Forwarded as a DISPLAY HINT
     * only (the frontend uses it to fail fast on cancel/decline) — never a state source.
     */
    private function statusHint(Request $request): ?string
    {
        $status = $request->query('status');

        return is_string($status) && in_array($status, self::STATUS_HINTS, true) ? $status : null;
    }

    private function withHint(string $url, ?string $hint): string
    {
        if ($hint === null) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'fedapay_status='.$hint;
    }
}
