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
        $status = Password::broker('admins')->sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'error' => [
                    'message' => 'Veuillez patienter avant de réessayer',
                    'code' => 'THROTTLED',
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Always return success to prevent email enumeration (OWASP best practice)
        return response()->json([
            'data' => null,
            'message' => 'Email de réinitialisation envoyé',
        ], Response::HTTP_OK);
    }
}
