<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use App\Notifications\AdminTwoFactorChangedNotification;
use App\Notifications\ResetPasswordNotification;
use App\Services\Admin\AdminTwoFactorService;
use App\Support\PasswordTimingGuard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Wave 2 of the admin 2FA hardening (independent security review findings).
 */
class AdminTwoFactorHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private function totp(int $stepOffset = 0): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp(self::SECRET, $google2fa->getTimestamp() + $stepOffset);
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function enrolledAdmin(array $overrides = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'email' => 'admin@test.com',
            'password' => 'SecurePass123',
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
        ], $overrides));
    }

    // ------------------------------------------------ 1. token abilities

    public function test_legacy_token_without_2fa_ability_loses_access_once_the_account_has_2fa(): void
    {
        $admin = $this->enrolledAdmin();
        // Deploy-day token: default abilities ['*'] (issued before 2FA existed).
        $legacy = $admin->createToken('admin-token')->plainTextToken;

        $this->getJson('/api/v1/admin/me', $this->bearer($legacy))
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'ADMIN_2FA_SESSION_INVALID');

        // Logout stays reachable so the SPA can drop the session.
        $this->postJson('/api/v1/admin/logout', [], $this->bearer($legacy))->assertOk();
    }

    public function test_token_issued_by_the_second_step_has_the_2fa_ability_and_works(): void
    {
        $this->enrolledAdmin();
        $challenge = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com', 'password' => 'SecurePass123',
        ])->json('data.challenge');

        $token = $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge, 'code' => $this->totp(),
        ])->assertOk()->json('data.token');

        $this->getJson('/api/v1/admin/me', $this->bearer($token))->assertOk();
        $this->assertContains('2fa', Admin::first()->tokens()->first()->abilities);
    }

    public function test_pre_enrolment_token_is_refused_after_enrolment_and_the_confirm_token_works(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);
        $old = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com', 'password' => 'SecurePass123',
        ])->assertOk()->json('data.token');
        $headers = $this->bearer($old);

        $secret = $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'SecurePass123'], $headers)
            ->json('data.secret');
        $response = $this->postJson('/api/v1/admin/two-factor/confirm', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ], $headers)->assertOk();

        $new = $response->json('data.token');
        $this->assertNotEmpty($new);

        $this->getJson('/api/v1/admin/me', $headers)->assertStatus(401);
        $this->getJson('/api/v1/admin/me', $this->bearer($new))->assertOk();
    }

    public function test_confirm_revokes_every_other_token_of_the_admin(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create(['password' => 'SecurePass123']);
        $current = $admin->createToken('admin-token', ['enrol'])->plainTextToken;
        $admin->createToken('stolen-1', ['enrol']);
        $admin->createToken('stolen-2');
        $headers = $this->bearer($current);

        $secret = $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'SecurePass123'], $headers)
            ->json('data.secret');
        $this->postJson('/api/v1/admin/two-factor/confirm', [
            'code' => (new Google2FA)->getCurrentOtp($secret),
        ], $headers)->assertOk();

        $this->assertSame(1, $admin->tokens()->count());
    }

    public function test_disable_and_regenerate_revoke_other_tokens_but_keep_the_current_one(): void
    {
        $admin = $this->enrolledAdmin();
        $current = $admin->createToken('admin-token', ['2fa'])->plainTextToken;
        $admin->createToken('other', ['2fa']);
        $headers = $this->bearer($current);

        $this->postJson('/api/v1/admin/two-factor/recovery-codes', [
            'password' => 'SecurePass123', 'code' => $this->totp(),
        ], $headers)->assertOk();
        $this->assertSame(1, $admin->tokens()->count());

        $admin->createToken('other-2', ['2fa']);
        $this->postJson('/api/v1/admin/two-factor/disable', [
            'password' => 'SecurePass123', 'code' => $this->totp(1),
        ], $headers)->assertOk();
        $this->assertSame(1, $admin->fresh()->tokens()->count());
    }

    // ------------------------------------------- 2. password + notification

    public function test_enable_requires_the_current_password(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create(['password' => 'SecurePass123']);
        $headers = $this->bearer($admin->createToken('admin-token', ['enrol'])->plainTextToken);

        $this->postJson('/api/v1/admin/two-factor/enable', [], $headers)->assertStatus(422);

        $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'wrong'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PASSWORD');
        $this->assertNull($admin->fresh()->two_factor_secret);

        $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'SecurePass123'], $headers)
            ->assertOk();
        $this->assertNotNull($admin->fresh()->two_factor_secret);
    }

    public function test_notification_is_queued_french_mail(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new AdminTwoFactorChangedNotification('enabled'));

        $mail = (new AdminTwoFactorChangedNotification('disabled'))->toMail(Admin::factory()->make());
        $this->assertSame(['mail'], (new AdminTwoFactorChangedNotification('enabled'))->via(new Admin));
        $this->assertStringContainsString('désactivée', $mail->subject);
    }

    public function test_admin_is_notified_on_enable_disable_regenerate_and_reset(): void
    {
        Notification::fake();
        $admin = Admin::factory()->withoutTwoFactor()->create(['password' => 'SecurePass123']);
        $headers = $this->bearer($admin->createToken('admin-token', ['enrol'])->plainTextToken);

        $secret = $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'SecurePass123'], $headers)
            ->json('data.secret');
        $google2fa = new Google2FA;
        $confirm = $this->postJson('/api/v1/admin/two-factor/confirm', [
            'code' => $google2fa->getCurrentOtp($secret),
        ], $headers)->assertOk();
        $headers = $this->bearer($confirm->json('data.token'));

        $this->assertNotified($admin, 'enabled');

        $step = $google2fa->getTimestamp();
        $regenerated = $this->postJson('/api/v1/admin/two-factor/recovery-codes', [
            'password' => 'SecurePass123', 'code' => $google2fa->oathTotp($secret, $step + 1),
        ], $headers)->assertOk();
        $this->assertNotified($admin, 'recovery_codes_regenerated');

        $this->postJson('/api/v1/admin/two-factor/disable', [
            'password' => 'SecurePass123', 'recovery_code' => $regenerated->json('data.recovery_codes.0'),
        ], $headers)->assertOk();
        $this->assertNotified($admin, 'disabled');

        $target = $this->enrolledAdmin(['email' => 'target@test.com']);
        $super = Admin::factory()->superAdmin()->create();
        $this->postJson("/api/v1/admin/admins/{$target->uuid}/two-factor/reset", [],
            $this->bearer($super->createToken('admin-token', ['2fa'])->plainTextToken))->assertOk();
        $this->assertNotified($target, 'reset');
    }

    private function assertNotified(Admin $admin, string $event): void
    {
        Notification::assertSentTo(
            $admin,
            AdminTwoFactorChangedNotification::class,
            fn ($notification) => $notification->event === $event,
        );
    }

    // ------------------------------------------------- 3. timing guard

    public function test_unknown_user_paths_never_compute_a_hash_at_request_time(): void
    {
        $hash = Hash::partialMock();
        $hash->shouldReceive('make')->never();
        $hash->shouldReceive('check')->twice()->andReturn(false);

        $this->assertFalse(PasswordTimingGuard::check('whatever', null));
        $this->assertFalse(PasswordTimingGuard::check('whatever', ''));
    }

    public function test_dummy_hash_is_a_valid_bcrypt_hash_at_cost_12(): void
    {
        $info = password_get_info(PasswordTimingGuard::DUMMY_HASH);

        $this->assertSame('bcrypt', $info['algoName']);
        $this->assertSame(12, $info['options']['cost']);
    }

    public function test_login_endpoints_do_not_hash_for_unknown_accounts(): void
    {
        $hash = Hash::partialMock();
        $hash->shouldReceive('make')->never();
        $hash->shouldReceive('check')->andReturn(false);

        $this->postJson('/api/v1/admin/login', ['email' => 'ghost@test.com', 'password' => 'bad'])->assertStatus(401);
        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@test.com', 'password' => 'bad'])->assertStatus(401);
    }

    // ------------------------------------------------ 5. deferred resets

    public function test_reset_password_notifications_are_not_queued_so_the_token_never_lands_in_jobs(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new ResetPasswordNotification('t'));
        $this->assertNotInstanceOf(ShouldQueue::class, new AdminResetPasswordNotification('t'));
    }

    public function test_forgot_password_sends_after_the_response_without_touching_the_queue(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@test.com']);
        $admin = Admin::factory()->create(['email' => 'adm@test.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'known@test.com'])->assertOk();
        $this->postJson('/api/v1/admin/forgot-password', ['email' => 'adm@test.com'])->assertOk();

        // Delivered (deferred until after the response), never persisted as a job payload.
        Notification::assertSentTo($user, ResetPasswordNotification::class);
        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
        $this->assertDatabaseCount('jobs', 0);
    }

    // ----------------------------------------------------------- 7. races

    public function test_recovery_code_cannot_be_consumed_twice_through_stale_instances(): void
    {
        $this->enrolledAdmin();
        $first = Admin::first();
        $stale = Admin::first();
        $service = app(AdminTwoFactorService::class);

        $this->assertTrue($service->consumeRecoveryCode($first, 'aaaaa-bbbbb'));
        // Loaded BEFORE the first consumption: without a row lock + re-read it still sees the code.
        $this->assertFalse($service->consumeRecoveryCode($stale, 'aaaaa-bbbbb'));
        $this->assertSame(['ccccc-ddddd'], Admin::first()->two_factor_recovery_codes);
    }

    public function test_challenge_already_marked_spent_is_refused_even_with_a_valid_code(): void
    {
        $this->enrolledAdmin();
        $challenge = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com', 'password' => 'SecurePass123',
        ])->json('data.challenge');

        // A concurrent request won the atomic "spent" marker first.
        Cache::add('admin-2fa-challenge:'.hash('sha256', $challenge).':spent', true, 600);

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge, 'code' => $this->totp(),
        ])->assertStatus(401)->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
