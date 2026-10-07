<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Normalizer;

/**
 * Per-account limiters + failure journal for the admin authentication surface.
 *
 * Buckets (15 min decay):
 *  - account + IP        : 5 failures, password AND second factor. Locks the attacker's IP.
 *  - account alone       : 20 password failures. Backstop against IP rotation.
 *  - second factor alone : 5 code failures (account-wide), only cleared by a full
 *    successful 2FA login. Caps TOTP guessing by someone who knows the password,
 *    whatever the number of IPs.
 *
 * Known trade-off (not eliminated): anyone who knows an admin email can still lock
 * the account out remotely, by burning the account-wide buckets (20 password
 * failures, or 5 wrong codes once the password is known) from several IPs. The IP
 * split only prevents a SINGLE IP from doing it with 5 attempts. The mitigations
 * are the 15 minute decay, the security e-mail sent to the admin, and the
 * superadmin recovery path.
 *
 * The bucket key is ALWAYS derived from the normalized email (lowercase, NFKC,
 * accents stripped) and never from the admin id: whether the DB lookup finds the
 * admin depends on MySQL's collation, which does not fold the same characters as
 * Str::ascii (`ı`, `ø`, `ł`...), so keying on the id for a found admin would make
 * the lockout response an admin-existence oracle. Applied identically to unknown
 * emails.
 */
class AdminAuthThrottle
{
    public const MAX_ATTEMPTS_PER_IP = 5;

    public const MAX_ATTEMPTS_PER_ACCOUNT = 20;

    public const MAX_SECOND_FACTOR_FAILURES_PER_ACCOUNT = 5;

    /** Consecutive wrong codes (after a correct password) that trigger the security e-mail. */
    public const SECOND_FACTOR_ALERT_THRESHOLD = 3;

    public const DECAY_SECONDS = 900;

    /**
     * Lowercase + NFKC + strip accents/diacritics + trim.
     */
    public function normalizeEmail(string $email): string
    {
        $email = trim($email);

        if (class_exists(Normalizer::class)) {
            $email = Normalizer::normalize($email, Normalizer::FORM_KC) ?: $email;
        }

        return Str::lower(Str::ascii($email));
    }

    public function accountSubject(?Admin $admin, string $email): string
    {
        return 'email:'.$this->normalizeEmail($admin !== null ? $admin->email : $email);
    }

    public function accountKey(?Admin $admin, string $email): string
    {
        return 'admin-account:'.$this->accountSubject($admin, $email);
    }

    public function accountIpKey(?Admin $admin, string $email, string $ip): string
    {
        return $this->accountKey($admin, $email).'|'.$ip;
    }

    public function isLocked(?Admin $admin, string $email, string $ip): bool
    {
        return RateLimiter::tooManyAttempts($this->accountIpKey($admin, $email, $ip), self::MAX_ATTEMPTS_PER_IP)
            || RateLimiter::tooManyAttempts($this->accountKey($admin, $email), self::MAX_ATTEMPTS_PER_ACCOUNT);
    }

    public function retryAfter(?Admin $admin, string $email, string $ip): int
    {
        $waits = [];

        if (RateLimiter::tooManyAttempts($this->accountIpKey($admin, $email, $ip), self::MAX_ATTEMPTS_PER_IP)) {
            $waits[] = RateLimiter::availableIn($this->accountIpKey($admin, $email, $ip));
        }
        if (RateLimiter::tooManyAttempts($this->accountKey($admin, $email), self::MAX_ATTEMPTS_PER_ACCOUNT)) {
            $waits[] = RateLimiter::availableIn($this->accountKey($admin, $email));
        }

        return max(1, ...($waits ?: [1]));
    }

    public function secondFactorKey(?Admin $admin, string $email): string
    {
        return 'admin-2fa-code:'.$this->accountSubject($admin, $email);
    }

    private function streakKey(?Admin $admin, string $email): string
    {
        return 'admin-2fa-streak:'.$this->accountSubject($admin, $email);
    }

    /**
     * Lock check for the second-factor stage: the generic buckets OR the dedicated one.
     */
    public function isLockedForSecondFactor(?Admin $admin, string $email, string $ip): bool
    {
        return $this->isLocked($admin, $email, $ip)
            || RateLimiter::tooManyAttempts($this->secondFactorKey($admin, $email), self::MAX_SECOND_FACTOR_FAILURES_PER_ACCOUNT);
    }

    public function retryAfterForSecondFactor(?Admin $admin, string $email, string $ip): int
    {
        $wait = $this->retryAfter($admin, $email, $ip);

        if (RateLimiter::tooManyAttempts($this->secondFactorKey($admin, $email), self::MAX_SECOND_FACTOR_FAILURES_PER_ACCOUNT)) {
            $wait = max($wait, RateLimiter::availableIn($this->secondFactorKey($admin, $email)));
        }

        return $wait;
    }

    /**
     * Wrong TOTP/recovery code (always after a correct password). Counts toward the
     * account+IP bucket and the dedicated second-factor bucket (NOT the 20 password one).
     *
     * @return bool true when this failure reaches the alert threshold (caller sends the e-mail)
     */
    public function recordSecondFactorFailure(?Admin $admin, string $email, string $ip): bool
    {
        RateLimiter::hit($this->accountIpKey($admin, $email, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->secondFactorKey($admin, $email), self::DECAY_SECONDS);
        $streak = RateLimiter::hit($this->streakKey($admin, $email), self::DECAY_SECONDS);

        Log::warning('auth.admin.two_factor.failed', [
            'email_hash' => $this->emailFingerprint($email),
            'ip' => $ip,
        ]);

        return $streak === self::SECOND_FACTOR_ALERT_THRESHOLD;
    }

    /**
     * Full successful 2FA login: the only thing that clears the second-factor bucket.
     */
    public function clearSecondFactor(?Admin $admin, string $email): void
    {
        RateLimiter::clear($this->secondFactorKey($admin, $email));
        RateLimiter::clear($this->streakKey($admin, $email));
    }

    public function clear(?Admin $admin, string $email, string $ip): void
    {
        RateLimiter::clear($this->accountIpKey($admin, $email, $ip));
        RateLimiter::clear($this->accountKey($admin, $email));
    }

    /**
     * OWASP A09: journal a failed attempt (email fingerprinted, never in clear)
     * and count it toward both buckets.
     */
    public function recordFailure(string $event, ?Admin $admin, string $email, string $ip): void
    {
        RateLimiter::hit($this->accountIpKey($admin, $email, $ip), self::DECAY_SECONDS);
        RateLimiter::hit($this->accountKey($admin, $email), self::DECAY_SECONDS);

        Log::warning($event, [
            'email_hash' => $this->emailFingerprint($email),
            'ip' => $ip,
        ]);
    }

    public function logLockout(string $email, string $ip): void
    {
        Log::warning('auth.admin.login.throttled', [
            'email_hash' => $this->emailFingerprint($email),
            'ip' => $ip,
        ]);
    }

    public function emailFingerprint(string $email): string
    {
        return substr(hash('sha256', Str::lower(trim($email))), 0, 16);
    }
}
