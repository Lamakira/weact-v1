<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Security-relevant throttles use NAMED limiters keyed per purpose : an inline
 * `throttle:X,Y` shares one counter per user/IP across every route.
 */
class NamedRateLimitersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function middlewareOf(string $routeName): array
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Route {$routeName} missing");

        return $route->gatherMiddleware();
    }

    public function test_security_routes_use_named_limiters_not_inline_counters(): void
    {
        $expected = [
            'auth.login' => 'throttle:login',
            'auth.register.face' => 'throttle:register',
            'auth.register.producer' => 'throttle:register',
            'auth.forgot-password' => 'throttle:forgot-password',
            'auth.reset-password' => 'throttle:reset-password',
            'verification.send' => 'throttle:email-verification-resend',
            'verification.verify' => 'throttle:email-link',
            'email-change.request' => 'throttle:email-change',
            'email-change.confirm' => 'throttle:email-link',
            'password.update' => 'throttle:password-change',
            'admin.login' => 'throttle:admin-login',
            'admin.login.two-factor' => 'throttle:admin-two-factor',
            'admin.forgot-password' => 'throttle:admin-forgot-password',
            'admin.reset-password' => 'throttle:admin-reset-password',
        ];

        foreach ($expected as $routeName => $middleware) {
            $this->assertContains($middleware, $this->middlewareOf($routeName), $routeName);
        }
    }

    public function test_public_browsing_does_not_consume_the_register_allowance(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->getJson('/api/v1/auth/registration-status')->assertOk();
            $this->getJson('/api/v1/public/faces')->assertSuccessful();
        }

        // 5/min register allowance is intact (invalid payload => 422, never 429).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register/face', [])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/register/face', [])->assertStatus(429);
    }

    public function test_login_and_register_counters_are_isolated(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register/producer', [])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/register/producer', [])->assertStatus(429);

        // Register exhausted, login (same IP) is unaffected.
        $this->postJson('/api/v1/auth/login', ['email' => 'a@test.com', 'password' => 'x'])
            ->assertStatus(401);
    }

    public function test_register_face_and_producer_share_one_register_bucket_but_not_forgot_password(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register/face', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'a@test.com'])->assertOk();
    }

    public function test_generic_throttled_requests_do_not_shorten_the_password_change_window(): void
    {
        $user = User::factory()->create(['password' => 'OldPassw0rd!']);
        $headers = ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];

        // Burn a lot of unrelated `throttle:60,1` traffic for this user.
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/me/notifications', $headers)->assertOk();
        }

        // The password-change budget (5 / 10 min) is still whole.
        for ($i = 0; $i < 5; $i++) {
            $this->putJson('/api/v1/password', [
                'current_password' => 'wrong',
                'password' => 'NewPassw0rd!2026',
                'password_confirmation' => 'NewPassw0rd!2026',
            ], $headers)->assertStatus(422);
        }
        $this->putJson('/api/v1/password', [], $headers)->assertStatus(429);
    }

    public function test_admin_login_ip_limiter_is_independent_from_admin_forgot_password(): void
    {
        Admin::factory()->withoutTwoFactor()->create(['email' => 'admin@test.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/login', ['email' => "ghost{$i}@test.com", 'password' => 'bad'])
                ->assertStatus(401);
        }
        $this->postJson('/api/v1/admin/login', ['email' => 'ghost9@test.com', 'password' => 'bad'])
            ->assertStatus(429);

        $this->postJson('/api/v1/admin/forgot-password', ['email' => 'admin@test.com'])->assertOk();
    }
}
