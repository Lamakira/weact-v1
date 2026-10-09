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
use Illuminate\Support\Facades\Log;
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
        // After the response, never queued: the signed link must not be written to `jobs`.
        // A mail failure is logged there; it must neither reach terminate() nor change the response.
        dispatch(function () use ($user, $newEmail): void {
            try {
                Notification::route('mail', $newEmail)
                    ->notify(new VerifyEmailChangeNotification($newEmail, $user));
            } catch (\Throwable $e) {
                Log::warning('auth.email_change_verification_failed', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        })->afterResponse();

        // Informational notice to the old address (no token, no link): same pattern.
        dispatch(function () use ($user, $newEmail): void {
            try {
                $user->notify(new EmailChangeRequestedNotification($newEmail));
            } catch (\Throwable $e) {
                Log::warning('auth.email_change_notice_failed', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);
            }
        })->afterResponse();

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
        // Signature FIRST, then user lookup + hash: unknown user, wrong hash,
        // forged or expired signature all yield ONE generic error (no oracle on
        // user ids / pending emails). Past this gate the caller holds a link we
        // signed, so the state-specific answers below leak nothing.
        if (! $request->hasValidSignature()) {
            return $this->invalidLinkResponse();
        }

        $user = User::find($id);

        if (! $user) {
            return $this->invalidLinkResponse();
        }

        if (! $user->pending_email) {
            return response()->json([
                'error' => [
                    'code' => 'NO_PENDING_EMAIL_CHANGE',
                    'message' => 'Aucun changement d\'email en attente.',
                ],
            ], Response::HTTP_BAD_REQUEST);
        }

        if (! hash_equals(sha1($user->pending_email), $hash)) {
            return $this->invalidLinkResponse();
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

    private function invalidLinkResponse(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CONFIRMATION_LINK',
                'message' => 'Lien de confirmation invalide ou expiré. Veuillez refaire une demande de changement d\'email.',
            ],
        ], Response::HTTP_FORBIDDEN);
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
