<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Services\Admin\AdminAuthThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'JBSWY3DPEHPK3PXP';

    private const PASSWORD = 'SecurePass123';

    private function totp(?int $stepOffset = 0): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp(self::SECRET, $google2fa->getTimestamp() + $stepOffset);
    }

    private function twoFactorAdmin(array $overrides = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['aaaaa-bbbbb', 'ccccc-ddddd'],
        ], $overrides));
    }

    /**
     * Admin routes require a real bearer token (`api.token` reads the Authorization header).
     */
    private function actingAsAdmin(Admin $admin): void
    {
        $this->withToken($admin->createToken('admin-token', ['2fa'])->plainTextToken);
    }

    private function startChallenge(string $email = 'admin@test.com'): string
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);

        return (string) $response->json('data.challenge');
    }

    // ---------------------------------------------------------------- login

    public function test_login_with_confirmed_two_factor_returns_challenge_and_no_token(): void
    {
        $this->twoFactorAdmin();

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.token');
        $this->assertNotEmpty($response->json('data.challenge'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_without_two_factor_still_returns_token(): void
    {
        Admin::factory()->withoutTwoFactor()->create([
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@test.com',
            'password' => self::PASSWORD,
        ])->assertOk()
            ->assertJsonPath('data.admin.two_factor_enabled', false)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_two_factor_challenge_with_valid_totp_returns_token(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        $response = $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.admin.email', 'admin@test.com')
            ->assertJsonPath('data.admin.two_factor_enabled', true)
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_two_factor_accepts_adjacent_window_codes(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(-1),
        ])->assertOk();
    }

    public function test_two_factor_rejects_wrong_code_and_challenge_stays_usable(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => '000000',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ])->assertOk();
    }

    public function test_challenge_is_single_use(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ])->assertOk();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(1),
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_challenge_expires_after_five_minutes(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_unknown_challenge_is_refused(): void
    {
        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => 'does-not-exist',
            'code' => '123456',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_CHALLENGE_INVALID');
    }

    public function test_totp_code_cannot_be_replayed_within_its_window(): void
    {
        $this->twoFactorAdmin();
        $code = $this->totp();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $this->startChallenge(),
            'code' => $code,
        ])->assertOk();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $this->startChallenge(),
            'code' => $code,
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');
    }

    public function test_recovery_code_logs_in_once_and_is_consumed(): void
    {
        $admin = $this->twoFactorAdmin();

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $this->startChallenge(),
            'recovery_code' => 'aaaaa-bbbbb',
        ])->assertOk()->assertJsonStructure(['data' => ['token']]);

        $this->assertSame(['ccccc-ddddd'], $admin->fresh()->two_factor_recovery_codes);

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $this->startChallenge(),
            'recovery_code' => 'aaaaa-bbbbb',
        ])->assertStatus(401)
            ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');
    }

    public function test_two_factor_secret_is_encrypted_at_rest(): void
    {
        $admin = $this->twoFactorAdmin();

        $raw = DB::table('admins')->where('id', $admin->id)->value('two_factor_secret');
        $this->assertNotSame(self::SECRET, $raw);
        $this->assertStringNotContainsString(self::SECRET, (string) $raw);
    }

    public function test_wrong_two_factor_codes_count_toward_the_account_limit_and_are_logged(): void
    {
        $this->twoFactorAdmin();
        $challenge = $this->startChallenge();
        Log::spy();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/login/two-factor', [
                'challenge' => $challenge,
                'code' => '000000',
            ])->assertStatus(401);
        }

        Log::shouldHaveReceived('warning')
            ->with('auth.admin.two_factor.failed', \Mockery::on(
                fn ($ctx) => isset($ctx['email_hash'], $ctx['ip']) && ! str_contains(json_encode($ctx), 'admin@test.com')
            ))->times(5);

        // 6th attempt, even with the right code, is locked out.
        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ])->assertStatus(429)->assertJsonPath('error.code', 'THROTTLED');
    }

    // ------------------------------------------------------------ enrolment

    public function test_unenrolled_admin_is_blocked_with_2fa_required_on_admin_routes(): void
    {
        $this->actingAsAdmin(Admin::factory()->withoutTwoFactor()->create());

        $this->getJson('/api/v1/admin/me')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_2FA_REQUIRED');
        $this->getJson('/api/v1/admin/articles')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'ADMIN_2FA_REQUIRED');
    }

    public function test_unenrolled_admin_can_reach_enrolment_and_logout(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create();
        $token = $admin->createToken('admin-token', ['2fa'])->plainTextToken;
        $headers = ['Authorization' => 'Bearer '.$token];

        $this->getJson('/api/v1/admin/two-factor', $headers)
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
        $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'password'], $headers)->assertOk();
        $this->postJson('/api/v1/admin/logout', [], $headers)->assertOk();
    }

    public function test_enable_returns_secret_uri_and_qr_and_stays_pending_until_confirmed(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create(['email' => 'new@test.com']);
        $this->actingAsAdmin($admin);

        $response = $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'password']);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'otpauth_uri', 'qr_svg']]);
        $this->assertStringStartsWith('otpauth://totp/', $response->json('data.otpauth_uri'));
        $this->assertStringContainsString($response->json('data.secret'), $response->json('data.otpauth_uri'));
        $this->assertStringContainsString('<svg', $response->json('data.qr_svg'));

        $fresh = $admin->fresh();
        $this->assertSame($response->json('data.secret'), $fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_confirmed_at);
        $this->assertFalse($fresh->hasTwoFactorEnabled());
    }

    public function test_enable_is_refused_when_already_confirmed(): void
    {
        $this->actingAsAdmin($this->twoFactorAdmin());

        $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'password'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'TWO_FACTOR_ALREADY_ENABLED');
    }

    public function test_confirm_with_valid_code_enables_and_returns_recovery_codes_once(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create();
        $this->actingAsAdmin($admin);
        $secret = $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'password'])->json('data.secret');
        $google2fa = new Google2FA;

        $response = $this->postJson('/api/v1/admin/two-factor/confirm', [
            'code' => $google2fa->getCurrentOtp($secret),
        ]);

        $response->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $fresh = $admin->fresh();
        $this->assertTrue($fresh->hasTwoFactorEnabled());
        $this->assertSame($response->json('data.recovery_codes'), $fresh->two_factor_recovery_codes);

        // Confirmation revokes the old tokens and returns a fresh one.
        $this->assertNotEmpty($response->json('data.token'));

        // Recovery codes are never exposed again by the status endpoint.
        $this->getJson('/api/v1/admin/two-factor', ['Authorization' => 'Bearer '.$response->json('data.token')])
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.recovery_codes_remaining', 8)
            ->assertJsonMissingPath('data.recovery_codes');
    }

    public function test_confirm_with_wrong_code_is_refused(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create();
        $this->actingAsAdmin($admin);
        $this->postJson('/api/v1/admin/two-factor/enable', ['password' => 'password']);

        $this->postJson('/api/v1/admin/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_without_started_enrolment_is_refused(): void
    {
        $this->actingAsAdmin(Admin::factory()->withoutTwoFactor()->create());

        $this->postJson('/api/v1/admin/two-factor/confirm', ['code' => '123456'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'TWO_FACTOR_NOT_STARTED');
    }

    public function test_enrolled_admin_passes_the_enforcement_middleware(): void
    {
        $this->actingAsAdmin($this->twoFactorAdmin());

        $this->getJson('/api/v1/admin/me')->assertOk();
    }

    // ----------------------------------------------- disable / regenerate

    public function test_disable_requires_current_password_and_code(): void
    {
        $admin = $this->twoFactorAdmin();
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/admin/two-factor/disable', [
            'password' => 'wrong-password',
            'code' => $this->totp(),
        ])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_PASSWORD');

        $this->postJson('/api/v1/admin/two-factor/disable', [
            'password' => self::PASSWORD,
            'code' => '000000',
        ])->assertStatus(422)->assertJsonPath('error.code', 'TWO_FACTOR_INVALID_CODE');

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->postJson('/api/v1/admin/two-factor/disable', [
            'password' => self::PASSWORD,
            'code' => $this->totp(),
        ])->assertOk();

        $fresh = $admin->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
    }

    public function test_regenerate_recovery_codes_requires_password_and_code(): void
    {
        $admin = $this->twoFactorAdmin();
        $this->actingAsAdmin($admin);

        $this->postJson('/api/v1/admin/two-factor/recovery-codes', [
            'password' => self::PASSWORD,
            'code' => '000000',
        ])->assertStatus(422);

        $response = $this->postJson('/api/v1/admin/two-factor/recovery-codes', [
            'password' => self::PASSWORD,
            'code' => $this->totp(),
        ]);

        $response->assertOk()->assertJsonCount(8, 'data.recovery_codes');
        $this->assertNotContains('aaaaa-bbbbb', $admin->fresh()->two_factor_recovery_codes);
    }

    // ------------------------------------------------- superadmin reset

    public function test_superadmin_can_reset_another_admins_two_factor_and_it_is_audited(): void
    {
        $super = Admin::factory()->superAdmin()->create();
        $target = $this->twoFactorAdmin();
        $target->createToken('admin-token', ['2fa']);
        $this->actingAsAdmin($super);
        Log::spy();

        $this->postJson("/api/v1/admin/admins/{$target->uuid}/two-factor/reset")
            ->assertOk();

        $fresh = $target->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertSame(0, $fresh->tokens()->count());
        Log::shouldHaveReceived('warning')
            ->with('audit.admin.two_factor.reset', \Mockery::on(
                fn ($ctx) => $ctx['actor_admin_id'] === $super->id && $ctx['target_admin_id'] === $target->id
            ))->once();
    }

    public function test_non_superadmin_cannot_reset_two_factor(): void
    {
        $target = $this->twoFactorAdmin();
        $this->actingAsAdmin(Admin::factory()->create());

        $this->postJson("/api/v1/admin/admins/{$target->uuid}/two-factor/reset")
            ->assertStatus(403);
        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());
    }

    public function test_limiter_is_cleared_after_successful_full_login(): void
    {
        $admin = $this->twoFactorAdmin();
        $challenge = $this->startChallenge();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/admin/login/two-factor', [
                'challenge' => $challenge,
                'code' => '000000',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => $this->totp(),
        ])->assertOk();

        // 4 failures + 1 more would lock (5) if the counter had not been cleared.
        $challenge = $this->startChallenge();
        $this->postJson('/api/v1/admin/login/two-factor', [
            'challenge' => $challenge,
            'code' => '000000',
        ])->assertStatus(401);

        $this->assertFalse(app(AdminAuthThrottle::class)->isLocked($admin, 'admin@test.com', '127.0.0.1'));
    }
}
