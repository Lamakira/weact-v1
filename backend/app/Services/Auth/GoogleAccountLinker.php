<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a verified Google identity into one of three outcomes: log an existing
 * user in, link the identity to an existing local account, or hand the caller
 * enough to walk a brand-new user through the finalisation screen.
 *
 * These rules are the security core of the whole Google flow.
 */
class GoogleAccountLinker
{
    public const OUTCOME_AUTHENTICATED = 'authenticated';

    public const OUTCOME_NEEDS_COMPLETION = 'needs_completion';

    public const OUTCOME_ERROR = 'error';

    /**
     * @param  array{sub: string, email: string, email_verified: bool, given_name: string|null, family_name: string|null, name: string|null}  $identity
     * @return array{outcome: string, user?: User, is_new_user?: bool, profile?: array<string, mixed>, code?: string, message?: string, status?: int}
     */
    public function resolve(array $identity, string $intent): array
    {
        // 1. Known Google identity. The `sub` claim IS the identity — the handshake
        //    already proved it, so email_verified is irrelevant on this path.
        $byGoogleId = User::query()->where('google_id', $identity['sub'])->with('userable')->first();

        if ($byGoogleId !== null) {
            return $this->authenticateOrReject($byGoogleId, isNewUser: false);
        }

        // 2. Everything below leaps from "controls this Google account" to "owns
        //    this email address". That leap is only sound if Google attests the
        //    address — without the claim, anyone controlling a Workspace domain
        //    could claim victim@their-domain.
        if ($identity['email_verified'] !== true) {
            return [
                'outcome' => self::OUTCOME_ERROR,
                'code' => 'GOOGLE_EMAIL_UNVERIFIED',
                'message' => "Votre adresse Google n'est pas vérifiée. Vérifiez-la chez Google, puis réessayez.",
                'status' => 422,
            ];
        }

        // 3. Local account with the same address: link the identity to it.
        //    An anonymized account is correctly missed here — its email was
        //    rewritten to deleted_{id}@anonymized.weact.bj.
        $byEmail = User::query()->where('email', $identity['email'])->with('userable')->first();

        if ($byEmail !== null) {
            $result = $this->authenticateOrReject($byEmail, isNewUser: false);

            if ($result['outcome'] !== self::OUTCOME_AUTHENTICATED) {
                return $result;
            }

            $this->link($byEmail, $identity['sub']);

            return $result;
        }

        // 4. Brand-new account. Nothing is created here: Google returns neither a
        //    role nor a date of birth, and consent must be collected explicitly.
        if (! config('app.registration_enabled', true)) {
            return [
                'outcome' => self::OUTCOME_ERROR,
                'code' => 'registration_disabled',
                'message' => 'Les inscriptions sont temporairement suspendues. Veuillez réessayer ultérieurement.',
                'status' => 403,
            ];
        }

        return [
            'outcome' => self::OUTCOME_NEEDS_COMPLETION,
            'profile' => [
                'google_id' => $identity['sub'],
                'email' => $identity['email'],
                'intent' => $intent,
            ] + $this->splitName($identity),
        ];
    }

    /**
     * @return array{outcome: string, user?: User, is_new_user?: bool, code?: string, message?: string, status?: int}
     */
    private function authenticateOrReject(User $user, bool $isNewUser): array
    {
        // Google must never be a way around deactivation: mirror LoginService.
        if (! $user->is_active) {
            return [
                'outcome' => self::OUTCOME_ERROR,
                'code' => 'ACCOUNT_DEACTIVATED',
                'message' => 'Ce compte a été désactivé.',
                'status' => 403,
            ];
        }

        return [
            'outcome' => self::OUTCOME_AUTHENTICATED,
            'user' => $user,
            'is_new_user' => $isNewUser,
        ];
    }

    private function link(User $user, string $sub): void
    {
        // Explicit assignment: google_id is deliberately not mass assignable.
        $user->google_id = $sub;
        $user->google_linked_at = now();

        // Google just attested the address; our own verification mail would prove
        // the same fact with weaker assurance.
        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();

        Log::info('auth.google.linked', ['user_id' => $user->id]);
    }

    /**
     * Best-effort first/last name, used to prefill the finalisation screen.
     *
     * @param  array{sub: string, email: string, email_verified: bool, given_name: string|null, family_name: string|null, name: string|null}  $identity
     * @return array{prenom: string, nom: string}
     */
    private function splitName(array $identity): array
    {
        $given = trim((string) ($identity['given_name'] ?? ''));
        $family = trim((string) ($identity['family_name'] ?? ''));

        if ($given !== '' || $family !== '') {
            return ['prenom' => $given, 'nom' => $family];
        }

        $full = trim((string) ($identity['name'] ?? ''));

        if ($full !== '') {
            $tokens = preg_split('/\s+/', $full, -1, PREG_SPLIT_NO_EMPTY) ?: [];

            if (count($tokens) >= 2) {
                $last = array_pop($tokens);

                return ['prenom' => implode(' ', $tokens), 'nom' => $last];
            }

            return ['prenom' => $tokens[0] ?? '', 'nom' => ''];
        }

        // Last resort: the local part of the email. Face::displayName trims, so an
        // empty `nom` renders fine, and the completion meter picks it up.
        return ['prenom' => Str::before($identity['email'], '@'), 'nom' => ''];
    }
}
