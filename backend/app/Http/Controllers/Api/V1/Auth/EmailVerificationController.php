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
        // Signature FIRST, then user lookup + hash: unknown user, wrong hash,
        // forged or expired signature all yield ONE generic error, so the link
        // endpoint cannot be used to probe which user ids exist.
        if (! $request->hasValidSignature()) {
            return $this->invalidLinkResponse();
        }

        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return $this->invalidLinkResponse();
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

    private function invalidLinkResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_VERIFICATION_LINK',
                'message' => 'Lien de vérification invalide ou expiré. Veuillez en demander un nouveau.',
            ],
        ], Response::HTTP_FORBIDDEN);
    }
}
