<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResetPasswordController extends Controller
{
    /**
     * Handle password reset request - validate token and update password.
     */
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'data' => null,
                'message' => 'Mot de passe réinitialisé avec succès',
                'meta' => [],
            ], Response::HTTP_OK);
        }

        // Handle various error cases - all return the same user-facing message
        // to avoid information disclosure about token validity
        return response()->json([
            'error' => [
                'message' => 'Lien expiré ou invalide',
                'code' => 'INVALID_TOKEN',
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
