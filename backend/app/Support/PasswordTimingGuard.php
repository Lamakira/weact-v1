<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Equalises the cost of a login attempt whether the account exists or not.
 *
 * Skipping bcrypt for an unknown (or password-less) account makes the response
 * measurably faster, which is an account-enumeration oracle. When there is no
 * hash to compare against, a throw-away hash of the same cost is checked
 * instead and the result is discarded.
 */
final class PasswordTimingGuard
{
    /**
     * Precomputed bcrypt hash (cost 12 = the app's BCRYPT_ROUNDS) of a random,
     * discarded string. A constant, NOT computed per request: hashing lazily in
     * a static runs on every request without Octane, which would make the
     * unknown-account path cost two bcrypts (reversed timing leak).
     *
     * Generated once with:
     *   php -r 'echo password_hash("weact-timing-guard-dummy-password", PASSWORD_BCRYPT, ["cost" => 12]);'
     * Keep the cost in sync with BCRYPT_ROUNDS if that ever changes.
     */
    public const DUMMY_HASH = '$2y$12$Hzu.dQdLTx70V28rlBRTsuD6YN.pMVsoLue2JNFhBjVBuEuPVnOTu';

    public static function check(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            Hash::check($password, self::DUMMY_HASH);

            return false;
        }

        return Hash::check($password, $hash);
    }
}
