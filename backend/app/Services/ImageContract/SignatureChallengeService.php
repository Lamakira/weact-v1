<?php

declare(strict_types=1);

namespace App\Services\ImageContract;

use App\Enums\ErrorCodes;
use App\Exceptions\SignatureCodeException;
use App\Mail\SignatureCodeMail;
use App\Models\SignatureChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SignatureChallengeService
{
    public const PURPOSE_BOOKING_REQUEST = 'booking_request';

    public const PURPOSE_BOOKING_ACCEPT_PREFIX = 'booking_accept:';

    public const PURPOSE_MISSION_PUBLISH = 'mission_publish';

    public const PURPOSE_CANDIDATURE_CONFIRM_PREFIX = 'candidature_confirm:';

    public static function bookingAcceptPurpose(string $bookingUuid): string
    {
        return self::PURPOSE_BOOKING_ACCEPT_PREFIX.$bookingUuid;
    }

    public static function candidatureConfirmPurpose(string $candidatureUuid): string
    {
        return self::PURPOSE_CANDIDATURE_CONFIRM_PREFIX.$candidatureUuid;
    }

    /**
     * Émet un code (6 chiffres, 10 min) et l'envoie en synchrone.
     *
     * @param  array<string, mixed>|null  $guardianSummary  conditions montrées au parent qui signe
     *
     * @throws SignatureCodeException 429 trop tôt, 503 envoi impossible
     */
    public function issue(
        User $user,
        string $purpose,
        string $email,
        ?string $guardianName = null,
        ?string $guardianRelation = null,
        ?array $guardianSummary = null,
    ): SignatureChallenge {
        $delay = (int) config('image_contracts.resend_delay_seconds', 60);
        $ttl = (int) config('image_contracts.code_ttl_minutes', 10);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $challenge = DB::transaction(function () use ($user, $purpose, $email, $guardianName, $guardianRelation, $delay, $ttl, $code): SignatureChallenge {
            // Sérialise les envois d'un même utilisateur : deux clics rapides ne créent pas deux défis.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $pending = SignatureChallenge::query()
                ->where('user_id', $user->id)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->get();

            $latest = $pending->first();
            if ($latest !== null && $latest->created_at !== null) {
                $elapsed = (int) $latest->created_at->diffInSeconds(now(), true);
                if ($elapsed < $delay) {
                    throw new SignatureCodeException(
                        ErrorCodes::SignatureCodeTooSoon,
                        'Un code vient d\'être envoyé. Patientez avant d\'en demander un nouveau.',
                        429,
                        max(1, $delay - $elapsed),
                    );
                }
            }

            // Les défis non consommés précédents ne servent plus.
            foreach ($pending as $old) {
                $old->update(['expires_at' => now()]);
            }

            return SignatureChallenge::create([
                'user_id' => $user->id,
                'purpose' => $purpose,
                'email' => $email,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes($ttl),
                'attempts' => 0,
                'guardian_name' => $guardianName,
                'guardian_relation' => $guardianRelation,
            ]);
        });

        try {
            $user->loadMissing('userable');
            $signerName = trim((string) $user->userable->display_name);

            Mail::to($email)->send(new SignatureCodeMail(
                code: $code,
                ttlMinutes: $ttl,
                signerName: $signerName !== '' ? $signerName : 'le titulaire du compte',
                forGuardian: $guardianName !== null,
                guardianName: $guardianName,
                summary: $guardianName !== null ? $guardianSummary : null,
            ));
        } catch (\Throwable $e) {
            $challenge->delete();

            Log::warning('Signature code mail failed', [
                'user_id' => $user->id,
                'purpose' => $purpose,
                'error' => $e->getMessage(),
            ]);

            throw new SignatureCodeException(
                ErrorCodes::SignatureCodeSendFailed,
                'Impossible d\'envoyer le code pour le moment. Réessayez dans un instant.',
                503,
            );
        }

        return $challenge;
    }

    /**
     * Vérifie le code et consomme le défi. À appeler DANS la transaction de l'action signée.
     *
     * @throws SignatureCodeException
     */
    public function verify(User $user, string $purpose, string $code): SignatureChallenge
    {
        $result = $this->attempt($user, $purpose, $code);

        if ($result instanceof SignatureCodeException) {
            throw $result;
        }

        return $result;
    }

    /**
     * Comme verify(), mais RETOURNE l'échec au lieu de le lever : l'appelant, dans sa
     * transaction, peut alors la valider (le compteur de tentatives persiste) avant de lever.
     * Lever dans la transaction annulerait l'incrément et rendrait la limite contournable.
     *
     * Verrou sur la ligne : un code déjà consommé reste refusé sous double-clic.
     */
    public function attempt(
        User $user,
        string $purpose,
        string $code,
        bool $requireGuardian = false,
    ): SignatureChallenge|SignatureCodeException {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        $challenge = SignatureChallenge::query()
            ->where('user_id', $user->id)
            ->where('purpose', $purpose)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if ($challenge === null || $challenge->consumed_at !== null) {
            return new SignatureCodeException(
                ErrorCodes::SignatureCodeInvalid,
                'Ce code n\'est plus valable. Demandez un nouveau code.',
            );
        }

        if ($challenge->expires_at->isPast()) {
            return new SignatureCodeException(
                ErrorCodes::SignatureCodeExpired,
                'Ce code a expiré. Demandez un nouveau code.',
            );
        }

        if ($challenge->attempts >= (int) config('image_contracts.max_attempts', 5)) {
            return new SignatureCodeException(
                ErrorCodes::SignatureCodeTooManyAttempts,
                'Trop de tentatives. Demandez un nouveau code.',
            );
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            return new SignatureCodeException(
                ErrorCodes::SignatureCodeInvalid,
                'Ce code est incorrect.',
            );
        }

        // Face mineure : un code qui n'est pas parti à un parent ne vaut pas signature.
        if ($requireGuardian && $challenge->guardian_name === null) {
            return new SignatureCodeException(
                ErrorCodes::SignatureCodeInvalid,
                'Pour une Face de moins de 18 ans, le code doit être envoyé à un parent ou tuteur.',
            );
        }

        $challenge->update(['consumed_at' => now()]);

        return $challenge;
    }
}
