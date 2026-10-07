<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Per-account limiter + failure journal for the admin authentication surface.
 *
 * The key is the normalized email only (no IP): an attacker rotating IPs
 * cannot bypass it. It is shared by password failures and 2FA code failures,
 * and is applied identically to unknown emails so a lockout reveals nothing.
 */
class AdminAuthThrottle
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900;

    public function key(string $email): string
    {
        return 'admin-account:'.Str::lower(trim($email));
    }

    public function isLocked(string $email): bool
    {
        return RateLimiter::tooManyAttempts($this->key($email), self::MAX_ATTEMPTS);
    }

    public function retryAfter(string $email): int
    {
        return max(1, RateLimiter::availableIn($this->key($email)));
    }

    public function hit(string $email): void
    {
        RateLimiter::hit($this->key($email), self::DECAY_SECONDS);
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->key($email));
    }

    /**
     * OWASP A09: journal a failed attempt (email fingerprinted, never in clear)
     * and count it toward the per-account limit.
     */
    public function recordFailure(string $event, string $email, string $ip): void
    {
        $this->hit($email);

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
