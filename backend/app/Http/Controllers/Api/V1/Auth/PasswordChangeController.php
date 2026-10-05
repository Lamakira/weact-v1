<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use App\Services\Auth\GoogleOAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    /**
     * Update the authenticated user's password.
     */
    public function update(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Setting a first password (Google-only account): the ticket proves the
        // owner just re-authenticated. Consumed before anything is written, bound to
        // this user, single-use.
        if ($user->password === null) {
            $ticket = (string) $request->validated('reauth_token');

            if (! app(GoogleOAuthService::class)->consumeReauthToken($ticket, $user->id)) {
                return response()->json([
                    'error' => [
                        'code' => 'REAUTH_TOKEN_INVALID',
                        'message' => 'Confirmation expirée. Reprenez la confirmation avec Google.',
                    ],
                ], 422);
            }
        }

        $user->update([
            'password' => Hash::make($request->validated('new_password')),
        ]);

        // Invalidate all other sessions/tokens (keep current)
        $currentToken = $request->user()->currentAccessToken();
        $user->tokens()->where('id', '!=', $currentToken->id)->delete();

        $user->notify(new PasswordChangedNotification);

        return response()->json([
            'data' => ['password_changed' => true],
            'meta' => [],
            'message' => 'Votre mot de passe a été modifié avec succès.',
        ]);
    }
}
