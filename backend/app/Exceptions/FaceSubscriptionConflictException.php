<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FaceSubscriptionConflictException extends HttpException
{
    public function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct(statusCode: 409, message: $message);
    }

    public static function alreadyActive(): self
    {
        return new self(
            errorCode: 'ALREADY_ACTIVE',
            message: 'Cette Face a déjà un abonnement actif. Utilisez « Étendre » pour prolonger la période en cours.',
        );
    }

    public static function pendingPaymentExists(): self
    {
        return new self(
            errorCode: 'PENDING_PAYMENT_EXISTS',
            message: 'Un paiement est en attente pour cette Face. Annulez d\'abord l\'abonnement en attente avant l\'activation manuelle.',
        );
    }

    public static function notExtendable(): self
    {
        return new self(
            errorCode: 'NOT_EXTENDABLE',
            message: 'Seul un abonnement actif et non expiré peut être prolongé. Utilisez « Activer » pour redémarrer un abonnement terminé.',
        );
    }

    public static function notCancellable(): self
    {
        return new self(
            errorCode: 'NOT_CANCELLABLE',
            message: 'Cet abonnement ne peut pas être annulé : il est déjà dans un état terminal.',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
            ],
        ], $this->getStatusCode());
    }
}
