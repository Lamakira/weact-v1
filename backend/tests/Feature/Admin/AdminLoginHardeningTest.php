<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use App\Services\Admin\AdminAuthThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminLoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function failFrom(string $ip, string $email, string $password = 'bad')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/v1/admin/login', ['email' => $email, 'password' => $password]);
    }

    public function test_five_failures_from_one_ip_lock_that_ip_on_that_account(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);

        // The per-IP backstop (5/min) is not what trips here: space the hits over time.
        for ($i = 0; $i < 5; $i++) {
            $this->failFrom('10.0.0.1', 'Admin@Test.com')->assertStatus(401)->assertJsonPath('error.code', 'AUTH_FAILED');
        }
        $this->travel(2)->minutes();

        // Correct password from the same IP is refused while locked...
        $this->failFrom('10.0.0.1', 'admin@test.com', 'SecurePass123')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED')
            ->assertHeader('Retry-After');

        // ...but the legitimate admin from ANOTHER IP is not locked out by a remote attacker.
        $this->failFrom('10.0.0.99', 'admin@test.com', 'SecurePass123')->assertOk();
    }

    public function test_account_wide_limit_locks_every_ip_after_twenty_failures(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);

        // 20 failures from 20 different IPs: no IP bucket trips, the account-wide one does.
        for ($i = 1; $i <= 20; $i++) {
            $this->failFrom("10.1.0.{$i}", 'admin@test.com')->assertStatus(401);
        }

        $this->failFrom('10.1.0.200', 'admin@test.com', 'SecurePass123')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED');
    }

    public function test_thresholds_are_named_constants(): void
    {
        $this->assertSame(5, AdminAuthThrottle::MAX_ATTEMPTS_PER_IP);
        $this->assertSame(20, AdminAuthThrottle::MAX_ATTEMPTS_PER_ACCOUNT);
    }

    public function test_lockout_response_is_identical_for_unknown_email(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->failFrom('10.0.1.1', 'ghost@test.com')->assertStatus(401);
        }
        $this->travel(2)->minutes();

        $this->failFrom('10.0.1.1', 'ghost@test.com')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED');
    }

    public function test_successful_login_resets_the_account_counters(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);

        for ($i = 1; $i <= 4; $i++) {
            $this->failFrom('10.0.2.1', 'admin@test.com')->assertStatus(401);
        }
        $this->travel(2)->minutes();

        $this->failFrom('10.0.2.1', 'admin@test.com', 'SecurePass123')->assertOk();
        $this->failFrom('10.0.2.1', 'admin@test.com')->assertStatus(401);
        $this->assertFalse(
            app(AdminAuthThrottle::class)->isLocked(Admin::first(), 'admin@test.com', '10.0.2.1')
        );
    }

    public function test_accent_variant_of_an_existing_admin_email_shares_the_same_bucket(): void
    {
        $admin = Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com']);
        $throttle = app(AdminAuthThrottle::class);

        // Same admin (the DB collation is accent-insensitive) => same key, whatever the spelling.
        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('auth.admin.login.failed', $admin, 'àdmin@test.com', '10.0.3.1');
        }

        $this->assertTrue($throttle->isLocked($admin, 'admin@test.com', '10.0.3.1'));
        $this->assertTrue($throttle->isLocked($admin, 'ÀDMIN@TEST.COM', '10.0.3.1'));
    }

    public function test_accent_variant_of_an_unknown_email_shares_the_same_bucket(): void
    {
        $throttle = app(AdminAuthThrottle::class);

        for ($i = 0; $i < 5; $i++) {
            $throttle->recordFailure('auth.admin.login.failed', null, 'ghöst@test.com', '10.0.3.2');
        }

        $this->assertTrue($throttle->isLocked(null, 'ghost@test.com', '10.0.3.2'));
        $this->assertTrue($throttle->isLocked(null, ' GHOST@test.com ', '10.0.3.2'));
    }

    public function test_failed_admin_login_is_logged_with_hashed_email_and_ip(): void
    {
        Log::spy();

        $this->postJson('/api/v1/admin/login', ['email' => 'Ghost@Test.com', 'password' => 'bad'])
            ->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->with('auth.admin.login.failed', \Mockery::on(
                fn ($ctx) => $ctx['email_hash'] === substr(hash('sha256', 'ghost@test.com'), 0, 16)
                    && isset($ctx['ip'])
                    && ! str_contains(json_encode($ctx), 'ghost@test.com')
            ))->once();
    }

    public function test_unknown_admin_email_still_runs_a_password_hash_check(): void
    {
        Hash::partialMock()->shouldReceive('check')->atLeast()->once()->andReturn(false);

        $this->postJson('/api/v1/admin/login', ['email' => 'ghost@test.com', 'password' => 'bad'])
            ->assertStatus(401);
    }

    public function test_unknown_user_email_still_runs_a_password_hash_check(): void
    {
        Hash::partialMock()->shouldReceive('check')->atLeast()->once()->andReturn(false);

        $this->postJson('/api/v1/auth/login', ['email' => 'ghost@test.com', 'password' => 'bad'])
            ->assertStatus(401);
    }

    public function test_oauth_only_user_still_runs_a_password_hash_check(): void
    {
        User::factory()->create(['email' => 'oauth@test.com', 'password' => null]);
        Hash::partialMock()->shouldReceive('check')->atLeast()->once()->andReturn(false);

        $this->postJson('/api/v1/auth/login', ['email' => 'oauth@test.com', 'password' => 'bad'])
            ->assertStatus(401);
    }
}
