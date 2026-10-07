<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Symfony\Component\HttpFoundation\Response;

class AdminForgotPasswordController extends Controller
{
    /**
     * Handle admin forgot password request — send password reset link via email.
     */
    public function __invoke(AdminForgotPasswordRequest $request): JsonResponse
    {
        // Same generic success whether the account exists, is unknown or is
        // throttled by the broker (RESET_THROTTLED would reveal the account).
        Password::broker('admins')->sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'data' => null,
            'message' => 'Email de réinitialisation envoyé',
        ], Response::HTTP_OK);
    }
}
