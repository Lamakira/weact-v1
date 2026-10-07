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
        // OWASP: the response must not depend on the account. RESET_LINK_SENT,
        // INVALID_USER AND RESET_THROTTLED (broker-side, only reachable for an
        // existing account) all return the very same generic success, otherwise
        // the throttled answer is an account-enumeration oracle.
        Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'data' => null,
            'message' => 'Email envoyé',
            'meta' => [],
        ], Response::HTTP_OK);
    }
}
