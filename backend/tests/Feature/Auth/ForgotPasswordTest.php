<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test password reset email contains correct frontend URL format.
     */
    public function test_reset_email_contains_correct_frontend_url(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
            $mailMessage = $notification->toMail($user);
            $actionUrl = $mailMessage->actionUrl;

            // Verify URL format: {FRONTEND_URL}/reset-password/{token}?email={email}
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $this->assertStringStartsWith($frontendUrl.'/reset-password/', $actionUrl);
            $this->assertStringContainsString('?email='.urlencode($user->email), $actionUrl);

            return true;
        });
    }

    /**
     * Test forgot password with valid email sends password reset email.
     */
    public function test_forgot_password_sends_email_for_valid_email(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => null,
                'message' => 'Email envoyé',
                'meta' => [],
            ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * Test forgot password with non-existent email returns 200 (prevents email enumeration).
     * OWASP Best Practice: Always return success to prevent attackers from discovering valid emails.
     */
    public function test_forgot_password_with_nonexistent_email_returns_success(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        // Should return success to prevent email enumeration
        $response->assertStatus(200)
            ->assertJson([
                'data' => null,
                'message' => 'Email envoyé',
                'meta' => [],
            ]);

        // But no notification should be sent
        Notification::assertNothingSent();
    }

    /**
     * Test forgot password requires email field.
     */
    public function test_forgot_password_requires_email(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test forgot password with invalid email format returns 422.
     */
    public function test_forgot_password_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    /**
     * Test forgot password rate limiting (6th request within 1 minute fails).
     */
    public function test_forgot_password_rate_limiting(): void
    {
        $user = User::factory()->create([
            'email' => 'ratelimit@example.com',
        ]);

        // Make 5 requests (should all succeed or be handled)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'ratelimit@example.com',
            ]);
        }

        // 6th request should be rate limited (429)
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'ratelimit@example.com',
        ]);

        $response->assertStatus(429);
    }
}
