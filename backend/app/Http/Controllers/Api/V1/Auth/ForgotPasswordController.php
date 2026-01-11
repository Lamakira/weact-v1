<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class ForgotPasswordController extends Controller
{
    /**
     * Handle forgot password request - send password reset link via email.
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'data' => null,
                'message' => 'Email envoyé',
                'meta' => [],
            ], Response::HTTP_OK);
        }

        // Handle various error cases
        $errorMessage = match ($status) {
            Password::INVALID_USER => 'Aucun compte associé à cet email',
            Password::RESET_THROTTLED => 'Veuillez patienter avant de réessayer',
            default => 'Une erreur est survenue',
        };

        $errorCode = match ($status) {
            Password::INVALID_USER => 'USER_NOT_FOUND',
            Password::RESET_THROTTLED => 'THROTTLED',
            default => 'UNKNOWN_ERROR',
        };

        return response()->json([
            'error' => [
                'message' => $errorMessage,
                'code' => $errorCode,
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
