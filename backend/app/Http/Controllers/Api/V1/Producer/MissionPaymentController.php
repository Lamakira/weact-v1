<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\ErrorCodes;
use App\Enums\MissionPaymentStatus;
use App\Exceptions\MissionPaymentInitiationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mission\PayUgcMissionCommissionRequest;
use App\Http\Requests\Producer\ConfirmMissionSelectionRequest;
use App\Http\Resources\MissionResource;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use App\Services\Ugc\UgcCommissionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MissionPaymentController extends Controller
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly FedapayService $fedapayService,
        private readonly UgcCommissionPaymentService $ugcCommissionPaymentService,
    ) {}

    /**
     * Confirm face selection and initiate payment.
     *
     * POST /api/v1/producer/missions/{mission}/confirm-selection
     */
    public function confirmAndPay(ConfirmMissionSelectionRequest $request, Mission $mission): JsonResponse
    {
        try {
            /** @var string[] $candidatureIds */
            $candidatureIds = (array) $request->validated('candidature_ids', []);

            $result = $this->missionPaymentService->confirmAndInitiatePayment(
                $mission,
                $candidatureIds
            );
            $checkoutUrl = $result['checkout_url'] ?? $this->resolveMissionReturnUrl($mission);

            $message = $result['payment']->status === MissionPaymentStatus::Paid
                ? 'Paiement déjà confirmé. Redirection vers la mission...'
                : 'Sélection confirmée. Redirection vers le paiement...';

            return response()->json([
                'data' => [
                    'payment_id' => $result['payment']->id,
                    'montant_total' => $result['payment']->montant_total_producteur,
                    'nombre_faces' => $result['payment']->nombre_faces_retenues,
                    'checkout_url' => $checkoutUrl,
                    'status' => $result['payment']->status,
                ],
                'message' => $message,
            ]);
        } catch (MissionPaymentInitiationException $e) {
            return response()->json(
                ErrorCodes::PaymentInitiationFailed->envelope($e->getMessage()),
                422
            );
        }
        // ValidationException intentionally NOT caught here — the global
        // handler in bootstrap/app.php::withExceptions() normalizes it into
        // the double-format { error:{code,message,details}, errors, message }
        // (FIX-22.2 AC #4/#9).
    }

    private function resolveMissionReturnUrl(Mission $mission): string
    {
        // Prefer FRONTEND_URL (SPA origin); fall back to APP_URL so config:cache
        // without FRONTEND_URL still produces an absolute URL in production.
        $frontendUrl = rtrim(
            (string) config('app.frontend_url', (string) config('app.url', '')),
            '/'
        );

        if ($frontendUrl === '') {
            Log::warning('resolveMissionReturnUrl: both app.frontend_url and app.url are empty — returning relative URL', [
                'mission_id' => $mission->id,
                'mission_uuid' => $mission->uuid,
            ]);
        }

        return "{$frontendUrl}/producer/missions/{$mission->uuid}/candidatures?payment=confirmed";
    }

    /**
     * Get the payment status for a mission (polling endpoint).
     *
     * GET /api/v1/producer/missions/{mission}/payment-status
     */
    public function paymentStatus(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Producer::class || $user->userable_id !== $mission->producer_id) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        $payment = $mission->payment;

        if (! $payment) {
            return response()->json([
                'data' => [
                    'has_payment' => false,
                    'status' => null,
                    'is_trackable' => false,
                    'mission_status' => $mission->status,
                ],
            ]);
        }

        // Poll FedaPay if payment is still pending and a transaction was initiated
        if (
            $payment->status === MissionPaymentStatus::Pending
            && $payment->fedapay_transaction_id !== null
        ) {
            try {
                $transaction = $this->fedapayService->retrieveTransaction((int) $payment->fedapay_transaction_id);

                if ($transaction->status === 'approved') {
                    $payment = $this->missionPaymentService->markAsPaid(
                        $payment,
                        (string) ($transaction->reference ?? $payment->fedapay_transaction_id)
                    );
                }
            } catch (\Throwable $e) {
                // Non-fatal: return current status
            }
        }

        // A mission payment is trackable only when it is still pending AND we have
        // a real FedaPay transaction id to poll against. Any other state (no
        // transaction id, paid, failed, refunded) must surface as non-trackable
        // so the SPA does not render the "Paiement en attente de confirmation..."
        // banner or start polling on a stuck-pending record. See FIX-19.3.
        $isTrackable = $payment->status === MissionPaymentStatus::Pending
            && $payment->fedapay_transaction_id !== null;

        return response()->json([
            'data' => [
                'has_payment' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'is_trackable' => $isTrackable,
                'paid_at' => $payment->paid_at,
                'montant_total' => $payment->montant_total_producteur,
                'mission_status' => $mission->fresh()->status,
            ],
        ]);
    }

    /**
     * Initiate payment of the WeAct commission to publish a UGC mission (Producer only).
     * Charges `commission_ugc` only — no escrow, no MissionPayment (D-1.5.a/d).
     *
     * POST /api/v1/producer/missions/{mission}/pay-commission
     */
    public function payUgcCommission(PayUgcMissionCommissionRequest $request, Mission $mission): JsonResponse
    {
        $result = $this->ugcCommissionPaymentService->initiateForMission($mission);

        return response()->json([
            'data' => new MissionResource($result['mission']),
            'checkout_url' => $result['checkout_url'],
            'message' => 'Paiement de la commission initié',
        ]);
    }

    /**
     * Poll Fedapay and publish the UGC mission if the commission is approved
     * (fallback when the webhook is delayed). Idempotent.
     *
     * GET /api/v1/producer/missions/{mission}/commission-status
     */
    public function ugcCommissionStatus(Request $request, Mission $mission): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Producer::class || $user->userable_id !== $mission->producer_id) {
            abort(403, 'Cette action n\'est pas autorisée');
        }

        $mission = $this->ugcCommissionPaymentService->checkAndProcessMission($mission);

        return response()->json([
            'data' => new MissionResource($mission),
        ]);
    }
}
