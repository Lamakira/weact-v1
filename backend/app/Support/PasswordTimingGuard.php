<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
    private static ?string $dummyHash = null;

    public static function check(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            Hash::check($password, self::dummyHash());

            return false;
        }

        return Hash::check($password, $hash);
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }
}
