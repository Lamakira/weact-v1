<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Producer;

use App\Enums\MissionPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Producer\ConfirmMissionSelectionRequest;
use App\Models\Mission;
use App\Models\Producer;
use App\Services\FedapayService;
use App\Services\MissionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MissionPaymentController extends Controller
{
    public function __construct(
        private readonly MissionPaymentService $missionPaymentService,
        private readonly FedapayService $fedapayService,
    ) {}

    /**
     * Confirm face selection and initiate payment.
     *
     * POST /api/v1/producer/missions/{mission}/confirm-selection
     */
    public function confirmAndPay(ConfirmMissionSelectionRequest $request, Mission $mission): JsonResponse
    {
        try {
            $payment = $this->missionPaymentService->confirmSelection(
                $mission,
                $request->validated('candidature_ids')
            );

            $result = $this->missionPaymentService->initiatePayment($payment);

            return response()->json([
                'data' => [
                    'payment_id' => $result['payment']->id,
                    'montant_total' => $result['payment']->montant_total_producteur,
                    'nombre_faces' => $result['payment']->nombre_faces_retenues,
                    'checkout_url' => $result['checkout_url'],
                    'status' => $result['payment']->status,
                ],
                'message' => 'Sélection confirmée. Redirection vers le paiement...',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation.',
                'errors' => $e->errors(),
            ], 422);
        }
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

        return response()->json([
            'data' => [
                'has_payment' => true,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at,
                'montant_total' => $payment->montant_total_producteur,
                'mission_status' => $mission->fresh()->status,
            ],
        ]);
    }
}
