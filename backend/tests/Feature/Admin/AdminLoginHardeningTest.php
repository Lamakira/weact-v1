<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AdminLoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_account_limit_locks_after_five_failures_regardless_of_ip(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);

        // 5 failures from 5 different IPs: the per-IP throttle never trips, the account limiter does.
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/api/v1/admin/login', ['email' => 'Admin@Test.com', 'password' => 'bad'])
                ->assertStatus(401)
                ->assertJsonPath('error.code', 'AUTH_FAILED');
        }

        // Correct password from yet another IP is refused while locked.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/api/v1/admin/login', ['email' => 'admin@test.com', 'password' => 'SecurePass123'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'THROTTLED')
            ->assertHeader('Retry-After');
    }

    public function test_lockout_response_is_identical_for_unknown_email(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.1.{$i}"])
                ->postJson('/api/v1/admin/login', ['email' => 'ghost@test.com', 'password' => 'bad'])
                ->assertStatus(401);
        }

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.99'])
            ->postJson('/api/v1/admin/login', ['email' => 'ghost@test.com', 'password' => 'bad']);

        $response->assertStatus(429)->assertJsonPath('error.code', 'THROTTLED');
    }

    public function test_successful_login_resets_the_account_counter(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com', 'password' => 'SecurePass123']);

        for ($i = 1; $i <= 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.{$i}"])
                ->postJson('/api/v1/admin/login', ['email' => 'admin@test.com', 'password' => 'bad'])
                ->assertStatus(401);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.50'])
            ->postJson('/api/v1/admin/login', ['email' => 'admin@test.com', 'password' => 'SecurePass123'])
            ->assertOk();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.2.51'])
            ->postJson('/api/v1/admin/login', ['email' => 'admin@test.com', 'password' => 'bad'])
            ->assertStatus(401);
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
