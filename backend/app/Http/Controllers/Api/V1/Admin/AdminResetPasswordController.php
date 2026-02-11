<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminResetPasswordRequest;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminResetPasswordController extends Controller
{
    /**
     * Handle admin password reset — validate token and update password.
     */
    public function __invoke(AdminResetPasswordRequest $request): JsonResponse
    {
        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password): void {
                $admin->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $admin->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'data' => null,
                'message' => 'Mot de passe mis à jour avec succès',
            ], Response::HTTP_OK);
        }

        return response()->json([
            'error' => [
                'message' => 'Lien expiré ou invalide',
                'code' => 'INVALID_TOKEN',
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
