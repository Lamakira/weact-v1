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
        if ($this->throttle->isLocked($email)) {
            $this->throttle->logLockout($email, $ip);

            return ['status' => 'locked', 'retry_after' => $this->throttle->retryAfter($email)];
        }

        $admin = Admin::where('email', $email)->first();

        // Always runs one bcrypt check, even for an unknown email (timing parity).
        $passwordValid = PasswordTimingGuard::check($password, $admin?->password);

        if ($admin === null || ! $passwordValid) {
            $this->throttle->recordFailure('auth.admin.login.failed', $email, $ip);

            return ['status' => 'invalid'];
        }

        if ($admin->hasTwoFactorEnabled()) {
            return ['status' => 'two_factor', 'challenge' => $this->issueChallenge($admin)];
        }

        $this->throttle->clear($email);

        return [
            'status' => 'ok',
            'admin' => $admin,
            'token' => $admin->createToken('admin-token')->plainTextToken,
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

        if ($this->throttle->isLocked($email)) {
            $this->throttle->logLockout($email, $ip);

            return ['status' => 'locked', 'retry_after' => $this->throttle->retryAfter($email)];
        }

        if (! $this->twoFactor->verifyCodeOrRecovery($admin, $code, $recoveryCode)) {
            $this->throttle->recordFailure('auth.admin.two_factor.failed', $email, $ip);

            return ['status' => 'invalid_code'];
        }

        // Single use: pull() returns null if a concurrent request already consumed it.
        if (Cache::pull($this->challengeKey($challenge)) === null) {
            return ['status' => 'challenge_invalid'];
        }

        $this->throttle->clear($email);

        return [
            'status' => 'ok',
            'admin' => $admin,
            'token' => $admin->createToken('admin-token')->plainTextToken,
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
