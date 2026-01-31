<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailVerificationController extends Controller
{
    /**
     * Resend the email verification notification.
     */
    public function sendVerificationNotification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'data' => ['verified' => true],
                'meta' => [],
                'message' => 'Votre email est déjà vérifié.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => ['sent' => true],
            'meta' => [],
            'message' => 'Un nouveau lien de vérification a été envoyé.',
        ]);
    }

    /**
     * Mark the user's email as verified.
     */
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'Utilisateur non trouvé.',
                ],
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify the hash matches the user's email
        if (!hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_VERIFICATION_LINK',
                    'message' => 'Lien de vérification invalide.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the link has expired (signature validation)
        if (!$request->hasValidSignature()) {
            return response()->json([
                'error' => [
                    'code' => 'VERIFICATION_LINK_EXPIRED',
                    'message' => 'Le lien de vérification a expiré. Veuillez en demander un nouveau.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'data' => ['verified' => true, 'already_verified' => true],
                'meta' => [],
                'message' => 'Votre email est déjà vérifié.',
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'data' => ['verified' => true],
            'meta' => [],
            'message' => 'Votre email a été vérifié avec succès.',
        ]);
    }

    /**
     * Get the current email verification status.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'verified' => $user->hasVerifiedEmail(),
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            ],
            'meta' => [],
            'message' => $user->hasVerifiedEmail()
                ? 'Email vérifié.'
                : 'Email non vérifié.',
        ]);
    }
}
