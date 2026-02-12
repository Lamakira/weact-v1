<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $face = Face::factory()->create();
        $this->user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email' => 'user@example.com',
            'password' => Hash::make('OldPassword1'),
        ]);
    }

    public function test_change_password_succeeds_with_valid_data(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'NewPassword2',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => ['password_changed' => true],
                'message' => 'Votre mot de passe a été modifié avec succès.',
            ]);

        // Verify password was actually changed
        $this->user->refresh();
        $this->assertTrue(Hash::check('NewPassword2', $this->user->password));
        $this->assertFalse(Hash::check('OldPassword1', $this->user->password));

        // Verify notification was sent
        Notification::assertSentTo($this->user, PasswordChangedNotification::class);
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'WrongPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'NewPassword2',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.current_password.0', 'Le mot de passe actuel est incorrect.');

        // Verify password was NOT changed
        $this->assertTrue(Hash::check('OldPassword1', $this->user->fresh()->password));
    }

    public function test_change_password_rejects_too_short_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'Short1',
                'new_password_confirmation' => 'Short1',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.new_password.0', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
    }

    public function test_change_password_rejects_password_without_uppercase(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'newpassword1',
                'new_password_confirmation' => 'newpassword1',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.new_password.0', 'Le nouveau mot de passe doit contenir au moins une majuscule et un chiffre.');
    }

    public function test_change_password_rejects_password_without_digit(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword',
                'new_password_confirmation' => 'NewPassword',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.new_password.0', 'Le nouveau mot de passe doit contenir au moins une majuscule et un chiffre.');
    }

    public function test_change_password_rejects_mismatched_confirmation(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'DifferentPassword3',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.new_password.0', 'La confirmation du nouveau mot de passe ne correspond pas.');
    }

    public function test_change_password_requires_authentication(): void
    {
        $response = $this->putJson('/api/v1/password', [
            'current_password' => 'OldPassword1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $response->assertUnauthorized();
    }

    public function test_change_password_requires_all_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', []);

        $response->assertUnprocessable();
        $this->assertArrayHasKey('current_password', $response->json('error.details'));
        $this->assertArrayHasKey('new_password', $response->json('error.details'));
    }

    public function test_change_password_rejects_same_password(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'OldPassword1',
                'new_password_confirmation' => 'OldPassword1',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.new_password.0', 'Le nouveau mot de passe doit être différent de l\'ancien.');
    }

    public function test_change_password_invalidates_other_sessions(): void
    {
        // Create multiple tokens for the user
        $otherToken = $this->user->createToken('other-device');
        $currentToken = $this->user->createToken('current-device');

        $response = $this->withHeader('Authorization', 'Bearer ' . $currentToken->plainTextToken)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'NewPassword2',
            ]);

        $response->assertOk();

        // Other token should be deleted, current should remain
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $currentToken->accessToken->id,
        ]);
    }

    public function test_change_password_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->user)
                ->putJson('/api/v1/password', [
                    'current_password' => 'WrongPassword' . $i,
                    'new_password' => 'NewPassword2',
                    'new_password_confirmation' => 'NewPassword2',
                ]);
        }

        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'OldPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'NewPassword2',
            ]);

        $response->assertStatus(429);
    }

    public function test_notification_not_sent_on_validation_failure(): void
    {
        $this->actingAs($this->user)
            ->putJson('/api/v1/password', [
                'current_password' => 'WrongPassword1',
                'new_password' => 'NewPassword2',
                'new_password_confirmation' => 'NewPassword2',
            ]);

        Notification::assertNotSentTo($this->user, PasswordChangedNotification::class);
    }
}
