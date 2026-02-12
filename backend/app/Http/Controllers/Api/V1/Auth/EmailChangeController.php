<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangeEmailRequest;
use App\Models\User;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\VerifyEmailChangeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\Response;

class EmailChangeController extends Controller
{
    /**
     * Request an email change. Stores pending_email and sends verification to new email.
     */
    public function requestChange(ChangeEmailRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $newEmail = $request->validated('email');

        $user->update(['pending_email' => $newEmail]);

        // Send verification to NEW email (not the user's current email)
        Notification::route('mail', $newEmail)
            ->notify(new VerifyEmailChangeNotification($newEmail, $user));

        // Send informational notification to old email
        $user->notify(new EmailChangeRequestedNotification($newEmail));

        return response()->json([
            'data' => ['pending_email' => $newEmail],
            'meta' => [],
            'message' => 'Un email de confirmation a été envoyé à votre nouvelle adresse.',
        ]);
    }

    /**
     * Confirm the email change via signed URL.
     */
    public function confirmChange(Request $request, int $id, string $hash): JsonResponse
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

        if (!$user->pending_email) {
            return response()->json([
                'error' => [
                    'code' => 'NO_PENDING_EMAIL_CHANGE',
                    'message' => 'Aucun changement d\'email en attente.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify the hash matches the pending email
        if (!hash_equals(sha1($user->pending_email), $hash)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CONFIRMATION_LINK',
                    'message' => 'Lien de confirmation invalide.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the link has expired (signature validation)
        if (!$request->hasValidSignature()) {
            return response()->json([
                'error' => [
                    'code' => 'CONFIRMATION_LINK_EXPIRED',
                    'message' => 'Le lien de confirmation a expiré. Veuillez faire une nouvelle demande.',
                ],
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if the new email is still available
        $emailTaken = User::where('email', $user->pending_email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailTaken) {
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_ALREADY_TAKEN',
                    'message' => 'Cette adresse email est désormais utilisée par un autre compte.',
                ],
            ], Response::HTTP_CONFLICT);
        }

        $user->update([
            'email' => $user->pending_email,
            'pending_email' => null,
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'data' => ['email_changed' => true],
            'meta' => [],
            'message' => 'Votre adresse email a été mise à jour avec succès.',
        ]);
    }

    /**
     * Cancel a pending email change.
     */
    public function cancelChange(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->update(['pending_email' => null]);

        return response()->json([
            'data' => ['cancelled' => true],
            'meta' => [],
            'message' => 'La demande de changement d\'email a été annulée.',
        ]);
    }

    /**
     * Get current pending email change status.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'pending_email' => $user->pending_email,
            ],
            'meta' => [],
            'message' => $user->pending_email
                ? 'Un changement d\'email est en attente de confirmation.'
                : 'Aucun changement d\'email en cours.',
        ]);
    }
}
