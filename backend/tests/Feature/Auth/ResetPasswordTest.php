<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test reset password with valid token updates password.
     */
    public function test_reset_password_with_valid_token_updates_password(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => null,
                'message' => 'Mot de passe réinitialisé avec succès',
                'meta' => [],
            ]);

        // Verify password was actually updated
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $user->password));
    }

    public function test_reset_password_rate_limiting(): void
    {
        $payload = [
            'token' => 'fake-token',
            'email' => 'ratelimit@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ];

        // The route throttle counts every request (regardless of payload).
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/reset-password', $payload);
        }

        // 6th request is rate limited (parity with forgot-password).
        $this->postJson('/api/v1/auth/reset-password', $payload)
            ->assertStatus(429);
    }

    /**
     * Test reset password with expired token returns 422.
     */
    public function test_reset_password_with_expired_token_returns_422(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        // Create a token and then travel forward in time past expiration
        $token = Password::createToken($user);

        // Manually delete the token to simulate expiration
        \DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'message' => 'Lien expiré ou invalide',
                    'code' => 'INVALID_TOKEN',
                ],
            ]);
    }

    /**
     * Test reset password with invalid token returns 422.
     */
    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token-that-does-not-exist',
            'email' => 'test@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'message' => 'Lien expiré ou invalide',
                    'code' => 'INVALID_TOKEN',
                ],
            ]);
    }

    /**
     * Test reset password enforces password complexity - requires uppercase.
     */
    public function test_reset_password_requires_uppercase_letter(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'newpassword1',  // No uppercase
            'password_confirmation' => 'newpassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test reset password enforces password complexity - requires number.
     */
    public function test_reset_password_requires_number(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword',  // No number
            'password_confirmation' => 'NewPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test reset password enforces minimum length.
     */
    public function test_reset_password_requires_minimum_length(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'Pass1',  // Too short
            'password_confirmation' => 'Pass1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test reset password requires confirmation.
     */
    public function test_reset_password_requires_confirmation(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $token = Password::createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'DifferentPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test reset password requires all fields.
     */
    public function test_reset_password_requires_all_fields(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * OWASP A07 (M-4): resetting the password must revoke all Sanctum tokens so a
     * stolen/leaked bearer token cannot survive the very recovery flow used to
     * reclaim a compromised account.
     */
    public function test_reset_password_revokes_all_sanctum_tokens(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        // Two live sessions (e.g. one stolen, one legitimate).
        $user->createToken('device-a');
        $user->createToken('device-b');
        $this->assertSame(2, $user->tokens()->count());

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'test@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertStatus(200);

        $this->assertSame(0, $user->tokens()->count());
    }
}
