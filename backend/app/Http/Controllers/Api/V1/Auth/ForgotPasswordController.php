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

        // OWASP Best Practice: Always return success message to prevent email enumeration.
        // Only exception is rate limiting (RESET_THROTTLED) which applies to all requests.
        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'error' => [
                    'message' => 'Veuillez patienter avant de réessayer',
                    'code' => 'THROTTLED',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // For all other statuses (RESET_LINK_SENT, INVALID_USER, etc.),
        // return success to prevent attackers from enumerating valid emails.
        return response()->json([
            'data' => null,
            'message' => 'Email envoyé',
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
