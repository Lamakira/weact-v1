<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Notifications\AdminSecondFactorGuessingNotification;
use App\Services\Admin\AdminAuthThrottle;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Second-factor brute force: a dedicated account-wide bucket (independent of the IP
 * and of the password bucket) + a security alert after 3 wrong codes in a row.
 */
class AdminSecondFactorBruteForceTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function totp(): string
    {
        return (new Google2FA)->getCurrentOtp(self::SECRET);
    }

    private function enrolledAdmin(): Admin
    {
        return Admin::factory()->create([
            'email' => 'admin@test.com',
            'password' => 'SecurePass123',
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb'],
        ]);
    }

    private function challengeFrom(string $ip): string
    {
        return (string) $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/admin/login', ['email' => 'admin@test.com', 'password' => 'SecurePass123'])
            ->json('data.challenge');
    }

    private function codeFrom(string $ip, string $challenge, string $code)
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => $code]);
    }

    public function test_threshold_is_a_named_constant(): void
    {
        $this->assertSame(5, AdminAuthThrottle::MAX_SECOND_FACTOR_FAILURES_PER_ACCOUNT);
        $this->assertSame(3, AdminAuthThrottle::SECOND_FACTOR_ALERT_THRESHOLD);
    }

    public function test_code_guesses_from_many_ips_are_capped_account_wide_at_five(): void
    {
        $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.2.0.1');

        // 5 wrong guesses, each from a different IP: no per-IP bucket trips (1 each).
        for ($i = 1; $i <= 5; $i++) {
            $this->codeFrom("10.2.1.{$i}", $challenge, '000000')->assertStatus(401);
        }

        // The 6th guess, even the right code, from a sixth IP is refused.
        $this->codeFrom('10.2.1.99', $challenge, $this->totp())
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED');
    }

    public function test_password_success_does_not_reset_the_second_factor_bucket(): void
    {
        $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.3.0.1');
        for ($i = 1; $i <= 5; $i++) {
            $this->codeFrom("10.3.1.{$i}", $challenge, '000000')->assertStatus(401);
        }

        // Correct password again (from another IP) yields a fresh challenge...
        $fresh = $this->challengeFrom('10.3.2.1');
        $this->assertNotEmpty($fresh);

        // ...but the code guesses stay capped.
        $this->codeFrom('10.3.2.1', $fresh, $this->totp())->assertStatus(429);
    }

    public function test_full_successful_login_clears_the_second_factor_bucket(): void
    {
        $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.4.0.1');
        for ($i = 1; $i <= 4; $i++) {
            $this->codeFrom("10.4.1.{$i}", $challenge, '000000')->assertStatus(401);
        }
        $this->codeFrom('10.4.2.1', $challenge, $this->totp())->assertOk();

        // 4 more failures would hit the cap of 5 if the bucket had not been cleared.
        $challenge = $this->challengeFrom('10.4.3.1');
        for ($i = 1; $i <= 4; $i++) {
            $this->codeFrom("10.4.4.{$i}", $challenge, '000000')->assertStatus(401);
        }
        $this->codeFrom('10.4.5.1', $challenge, '000000')->assertStatus(401);
    }

    public function test_second_factor_failures_do_not_consume_the_password_account_wide_bucket(): void
    {
        $admin = $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.5.0.1');
        for ($i = 1; $i <= 5; $i++) {
            $this->codeFrom("10.5.1.{$i}", $challenge, '000000')->assertStatus(401);
        }

        $throttle = app(AdminAuthThrottle::class);
        $this->assertSame(0, \Illuminate\Support\Facades\RateLimiter::attempts($throttle->accountKey($admin, $admin->email)));
    }

    public function test_admin_is_alerted_once_after_three_consecutive_wrong_codes(): void
    {
        Notification::fake();
        $admin = $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.6.0.1');

        $this->codeFrom('10.6.1.1', $challenge, '000000');
        $this->codeFrom('10.6.1.2', $challenge, '000000');
        Notification::assertNothingSent();

        $this->codeFrom('10.6.1.3', $challenge, '000000');
        Notification::assertSentToTimes($admin, AdminSecondFactorGuessingNotification::class, 1);

        $this->codeFrom('10.6.1.4', $challenge, '000000');
        Notification::assertSentToTimes($admin, AdminSecondFactorGuessingNotification::class, 1);
    }

    public function test_a_valid_code_resets_the_consecutive_failure_streak(): void
    {
        Notification::fake();
        $this->enrolledAdmin();
        $challenge = $this->challengeFrom('10.7.0.1');
        $this->codeFrom('10.7.1.1', $challenge, '000000');
        $this->codeFrom('10.7.1.2', $challenge, '000000');
        $this->codeFrom('10.7.1.3', $challenge, $this->totp())->assertOk();

        $challenge = $this->challengeFrom('10.7.2.1');
        $this->codeFrom('10.7.3.1', $challenge, '000000');
        $this->codeFrom('10.7.3.2', $challenge, '000000');

        Notification::assertNothingSent();
    }

    public function test_alert_is_a_queued_french_mail(): void
    {
        $notification = new AdminSecondFactorGuessingNotification;
        $mail = $notification->toMail(Admin::factory()->make());

        $this->assertInstanceOf(ShouldQueue::class, $notification);
        $this->assertSame(['mail'], $notification->via(new Admin));
        $this->assertStringContainsString('mot de passe', implode(' ', $mail->introLines));
        $this->assertStringContainsString('changez-le', implode(' ', $mail->introLines));
    }
}
