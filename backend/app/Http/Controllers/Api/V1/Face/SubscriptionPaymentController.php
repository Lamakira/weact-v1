<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Face;

use App\Enums\ErrorCodes;
use App\Enums\FaceSubscriptionPlan;
use App\Exceptions\FaceSubscriptionPaymentInitiationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Face\InitiateFaceSubscriptionPaymentRequest;
use App\Models\Face;
use App\Services\FaceSubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPaymentController extends Controller
{
    public function __construct(
        private readonly FaceSubscriptionPaymentService $paymentService,
    ) {}

    public function initiate(InitiateFaceSubscriptionPaymentRequest $request): JsonResponse
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

        $plan = FaceSubscriptionPlan::from((string) $request->validated('plan'));

        try {
            $result = $this->paymentService->initiate($face, $user, $plan);

            return response()->json([
                'data' => [
                    'subscription_id' => $result['subscription']->uuid,
                    'status' => $result['subscription']->status->value,
                    'plan' => $result['subscription']->plan->value,
                    'checkout_url' => $result['checkout_url'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'forfeited_days' => $result['forfeited_days'],
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

    public function cancelPending(Request $request): JsonResponse
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

        $subscription = $this->paymentService->cancelOwnPending($face);

        return response()->json([
            'data' => [
                'subscription_id' => $subscription->uuid,
                'status' => $subscription->status->value,
            ],
            'message' => 'Paiement annulé.',
        ]);
    }
}
