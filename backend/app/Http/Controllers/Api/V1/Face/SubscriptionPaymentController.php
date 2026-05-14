<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Exceptions\FaceSubscriptionPaymentInitiationException;
use App\Http\Controllers\Controller;
use App\Models\Face;
use App\Services\FaceSubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function __construct(
        private readonly FaceSubscriptionPaymentService $paymentService,
    ) {}

    public function initiate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        /** @var Face $face */
        $face = Face::query()->findOrFail($user->userable_id);

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Vous devez vérifier votre email pour effectuer cette action.',
                ],
            ], 403);
        }

        try {
            $result = $this->paymentService->initiate($face, $user);

            return response()->json([
                'data' => [
                    'subscription_id' => $result['subscription']->uuid,
                    'status' => $result['subscription']->status->value,
                    'checkout_url' => $result['checkout_url'],
                    'amount' => (int) config('face_premium.annual_plan.amount'),
                    'currency' => (string) config('face_premium.annual_plan.currency', 'XOF'),
                ],
                'message' => 'Redirection vers le paiement...',
            ]);
        } catch (FaceSubscriptionPaymentInitiationException) {
            return response()->json(
                ErrorCodes::PaymentInitiationFailed->envelope(
                    'Le paiement de l\'abonnement n\'a pas pu être initialisé. Veuillez réessayer.'
                ),
                502,
            );
        }
        // FaceSubscriptionConflictException (PENDING_PAYMENT_EXISTS 409,
        // PLAN_UNAVAILABLE 422) renders itself via its render(Request) method,
        // intercepted natively by Laravel 12's renderViaCallbacks() before any
        // global handler — see backend/app/Exceptions/FaceSubscriptionConflictException.php.
        //
        // ValidationException (cache lock timeout) propagates to the global
        // handler at backend/bootstrap/app.php.
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->userable_type !== Face::class) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Accès réservé aux Faces',
                ],
            ], 403);
        }

        /** @var Face $face */
        $face = Face::query()->findOrFail($user->userable_id);

        $subscription = $this->paymentService->checkAndProcessPayment($face);
        $subscription?->refresh();

        return response()->json([
            'data' => [
                'subscription_id' => $subscription?->uuid,
                'status' => $subscription ? $subscription->status->value : 'free',
            ],
        ]);
    }
}
