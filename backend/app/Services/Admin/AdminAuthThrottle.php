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
 * Two buckets, both fed by password failures AND 2FA code failures:
 *  - account + IP : 5 failures / 15 min. Locks the attacker's own IP only, so
 *    knowing an admin email is not enough to lock the real admin out from afar.
 *  - account alone: 20 failures / 15 min. Backstop against a distributed attack
 *    rotating IPs, deliberately much higher.
 *
 * The account subject is the admin id when the email resolves to an admin (the
 * lookup runs under an accent-insensitive collation, so spelling variants of one
 * address are one account), and a strongly normalized email otherwise. Applied
 * identically to unknown emails, so a lockout reveals nothing.
 */
class AdminAuthThrottle
{
    public const MAX_ATTEMPTS_PER_IP = 5;

    public const MAX_ATTEMPTS_PER_ACCOUNT = 20;

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
        return $admin !== null
            ? 'id:'.$admin->getKey()
            : 'email:'.$this->normalizeEmail($email);
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
