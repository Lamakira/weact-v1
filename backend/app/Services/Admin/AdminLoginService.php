<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Admin;
use App\Support\PasswordTimingGuard;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Service for handling admin login (password step + optional TOTP step).
 */
class AdminLoginService
{
    public const CHALLENGE_TTL_MINUTES = 5;

    /** Longer than the challenge TTL so an expired-then-replayed id stays refused. */
    private const SPENT_MARKER_TTL_SECONDS = 900;

    public function __construct(
        private readonly AdminAuthThrottle $throttle,
        private readonly AdminTwoFactorService $twoFactor,
    ) {}

    /**
     * Password step.
     *
     * Outcomes:
     *  - locked      : per-account limit reached (same for unknown emails)
     *  - invalid     : unknown email or wrong password (indistinguishable)
     *  - two_factor  : password OK, 2FA confirmed -> NO token, a challenge id
     *  - ok          : password OK, no 2FA enrolled yet -> token (route guard forces enrolment)
     *
     * @return array{status: 'locked', retry_after: int}|array{status: 'invalid'}|array{status: 'two_factor', challenge: string}|array{status: 'ok', admin: Admin, token: string}
     */
    public function login(string $email, string $password, string $ip): array
    {
        // Lookup first: the lock is keyed on the admin id (the DB collation is
        // accent-insensitive, so spelling variants of an email are one account).
        $admin = Admin::where('email', $email)->first();

        if ($this->throttle->isLocked($admin, $email, $ip)) {
            $this->throttle->logLockout($email, $ip);

            return ['status' => 'locked', 'retry_after' => $this->throttle->retryAfter($admin, $email, $ip)];
        }

        // Always runs one bcrypt check, even for an unknown email (timing parity).
        $passwordValid = PasswordTimingGuard::check($password, $admin?->password);

        if ($admin === null || ! $passwordValid) {
            $this->throttle->recordFailure('auth.admin.login.failed', $admin, $email, $ip);

            return ['status' => 'invalid'];
        }

        if ($admin->hasTwoFactorEnabled()) {
            return ['status' => 'two_factor', 'challenge' => $this->issueChallenge($admin)];
        }

        $this->throttle->clear($admin, $email, $ip);

        // No confirmed 2FA: enrolment-limited token (the `admin.2fa` middleware
        // refuses it everywhere except enrolment and logout).
        return [
            'status' => 'ok',
            'admin' => $admin,
            'token' => $admin->createToken('admin-token', [Admin::ABILITY_ENROLMENT])->plainTextToken,
        ];
    }

    /**
     * Second step: exchange the challenge + TOTP/recovery code for a token.
     *
     * @return array{status: 'locked', retry_after: int}|array{status: 'challenge_invalid'}|array{status: 'invalid_code'}|array{status: 'ok', admin: Admin, token: string}
     */
    public function completeTwoFactor(string $challenge, ?string $code, ?string $recoveryCode, string $ip): array
    {
        $adminId = Cache::get($this->challengeKey($challenge));
        $admin = $adminId !== null ? Admin::find($adminId) : null;

        if ($admin === null || ! $admin->hasTwoFactorEnabled()) {
            return ['status' => 'challenge_invalid'];
        }

        $email = $admin->email;

        if ($this->throttle->isLocked($admin, $email, $ip)) {
            $this->throttle->logLockout($email, $ip);

            return ['status' => 'locked', 'retry_after' => $this->throttle->retryAfter($admin, $email, $ip)];
        }

        if (! $this->twoFactor->verifyCodeOrRecovery($admin, $code, $recoveryCode)) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $admin, $email, $ip);

            return ['status' => 'invalid_code'];
        }

        // Single use, atomically: Cache::pull is get-then-forget and two concurrent
        // requests could both pass. Cache::add on a ":spent" marker has exactly one
        // winner (insertOrIgnore on the database store, SET NX on Redis).
        if (! Cache::add($this->challengeKey($challenge).':spent', true, self::SPENT_MARKER_TTL_SECONDS)) {
            return ['status' => 'challenge_invalid'];
        }
        Cache::forget($this->challengeKey($challenge));

        $this->throttle->clear($admin, $email, $ip);

        return [
            'status' => 'ok',
            'admin' => $admin,
            'token' => $admin->createToken('admin-token', [Admin::ABILITY_TWO_FACTOR])->plainTextToken,
        ];
    }

    private function issueChallenge(Admin $admin): string
    {
        $challenge = Str::random(64);

        Cache::put(
            $this->challengeKey($challenge),
            $admin->getKey(),
            now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        );

        return $challenge;
    }

    private function challengeKey(string $challenge): string
    {
        return 'admin-2fa-challenge:'.hash('sha256', $challenge);
    }
}
