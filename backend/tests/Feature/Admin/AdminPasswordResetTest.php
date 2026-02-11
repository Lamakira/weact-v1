<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    // ─── Forgot Password ───────────────────────────────────────────

    public function test_admin_forgot_password_sends_email(): void
    {
        Notification::fake();

        $admin = Admin::factory()->create(['email' => 'admin@weact.test']);

        $response = $this->postJson('/api/v1/admin/forgot-password', [
            'email' => 'admin@weact.test',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email de réinitialisation envoyé',
            ]);

        Notification::assertSentTo($admin, AdminResetPasswordNotification::class);
    }

    public function test_admin_forgot_password_invalid_email_returns_ok(): void
    {
        // OWASP: always returns success to prevent email enumeration
        $response = $this->postJson('/api/v1/admin/forgot-password', [
            'email' => 'nonexistent@weact.test',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Email de réinitialisation envoyé',
            ]);
    }

    public function test_admin_forgot_password_validation_missing_email(): void
    {
        $response = $this->postJson('/api/v1/admin/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_admin_forgot_password_validation_invalid_email_format(): void
    {
        $response = $this->postJson('/api/v1/admin/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    // ─── Reset Password ────────────────────────────────────────────

    public function test_admin_reset_password_with_valid_token(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@weact.test']);

        $token = Password::broker('admins')->createToken($admin);

        $response = $this->postJson('/api/v1/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@weact.test',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Mot de passe mis à jour avec succès',
            ]);

        // Verify admin can login with new password
        $admin->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $admin->password));
    }

    public function test_admin_reset_password_with_invalid_token_returns_422(): void
    {
        Admin::factory()->create(['email' => 'admin@weact.test']);

        $response = $this->postJson('/api/v1/admin/reset-password', [
            'token' => 'invalid-token',
            'email' => 'admin@weact.test',
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

    public function test_admin_reset_password_validation_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/admin/reset-password', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_admin_reset_password_validation_short_password(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@weact.test']);
        $token = Password::broker('admins')->createToken($admin);

        $response = $this->postJson('/api/v1/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@weact.test',
            'password' => 'Ab1',
            'password_confirmation' => 'Ab1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_admin_reset_password_validation_mismatch_confirmation(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@weact.test']);
        $token = Password::broker('admins')->createToken($admin);

        $response = $this->postJson('/api/v1/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@weact.test',
            'password' => 'NewPassword1',
            'password_confirmation' => 'DifferentPassword1',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_error');
    }

    public function test_admin_can_login_with_new_password_after_reset(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'admin@weact.test',
            'password' => Hash::make('OldPassword1'),
        ]);

        $token = Password::broker('admins')->createToken($admin);

        $this->postJson('/api/v1/admin/reset-password', [
            'token' => $token,
            'email' => 'admin@weact.test',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertOk();

        // Login with new password
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@weact.test',
            'password' => 'NewPassword1',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    // ─── SuperAdmin Trigger Reset ──────────────────────────────────

    public function test_superadmin_can_trigger_reset_for_admin(): void
    {
        Notification::fake();

        $superadmin = Admin::factory()->create(['role' => AdminRole::SuperAdmin]);
        $targetAdmin = Admin::factory()->create([
            'email' => 'target@weact.test',
            'role' => AdminRole::Admin,
        ]);

        $response = $this->actingAs($superadmin, 'sanctum')
            ->postJson("/api/v1/admin/admins/{$targetAdmin->id}/send-reset-link");

        $response->assertOk()
            ->assertJson([
                'message' => 'Lien de réinitialisation envoyé à target@weact.test',
            ]);

        Notification::assertSentTo($targetAdmin, AdminResetPasswordNotification::class);
    }

    public function test_non_superadmin_cannot_trigger_reset(): void
    {
        $admin = Admin::factory()->create(['role' => AdminRole::Admin]);
        $targetAdmin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/admins/{$targetAdmin->id}/send-reset-link");

        $response->assertForbidden();
    }

    public function test_editor_cannot_trigger_reset(): void
    {
        $editor = Admin::factory()->create(['role' => AdminRole::Editor]);
        $targetAdmin = Admin::factory()->create(['role' => AdminRole::Admin]);

        $response = $this->actingAs($editor, 'sanctum')
            ->postJson("/api/v1/admin/admins/{$targetAdmin->id}/send-reset-link");

        $response->assertForbidden();
    }

    // ─── Rate Limiting ─────────────────────────────────────────────

    public function test_forgot_password_is_rate_limited(): void
    {
        // Send 6 requests (limit is 5 per minute)
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/forgot-password', [
                'email' => "admin{$i}@weact.test",
            ]);
        }

        $response = $this->postJson('/api/v1/admin/forgot-password', [
            'email' => 'admin@weact.test',
        ]);

        $response->assertStatus(429);
    }

    // ─── Token isolation ───────────────────────────────────────────

    public function test_admin_token_does_not_work_for_user_reset(): void
    {
        $admin = Admin::factory()->create(['email' => 'admin@weact.test']);

        $token = Password::broker('admins')->createToken($admin);

        // Try to use admin token via the user password reset (default broker)
        $status = Password::reset(
            [
                'email' => 'admin@weact.test',
                'password' => 'NewPassword1',
                'password_confirmation' => 'NewPassword1',
                'token' => $token,
            ],
            function () {}
        );

        $this->assertNotEquals(Password::PASSWORD_RESET, $status);
    }
}
