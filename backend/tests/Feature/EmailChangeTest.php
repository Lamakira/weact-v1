<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\User;
use App\Notifications\EmailChangeRequestedNotification;
use App\Notifications\VerifyEmailChangeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailChangeTest extends TestCase
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
            'email' => 'old@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
        ]);
    }

    // === Request Email Change ===

    public function test_request_email_change_stores_pending_email(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/email/change', [
                'email' => 'new@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => ['pending_email' => 'new@example.com'],
                'message' => 'Un email de confirmation a été envoyé à votre nouvelle adresse.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'email' => 'old@example.com',
            'pending_email' => 'new@example.com',
        ]);

        // Verification email sent to NEW email via on-demand notification
        Notification::assertSentOnDemand(VerifyEmailChangeNotification::class, function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'new@example.com';
        });

        // Informational notification sent to OLD email (via user)
        Notification::assertSentTo($this->user, EmailChangeRequestedNotification::class);
    }

    public function test_request_email_change_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/email/change', [
                'email' => 'taken@example.com',
                'password' => 'password123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.email.0', 'Cette adresse email est déjà utilisée.');
    }

    public function test_request_email_change_rejects_wrong_password(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/email/change', [
                'email' => 'new@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.password.0', 'Le mot de passe est incorrect.');
    }

    public function test_request_email_change_rejects_same_email(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/email/change', [
                'email' => 'old@example.com',
                'password' => 'password123',
            ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error.details.email.0', "La nouvelle adresse email doit être différente de l'actuelle.");
    }

    public function test_request_email_change_overwrites_previous_pending(): void
    {
        $this->user->update(['pending_email' => 'first@example.com']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/email/change', [
                'email' => 'second@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'pending_email' => 'second@example.com',
        ]);
    }

    public function test_request_email_change_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/email/change', [
            'email' => 'new@example.com',
            'password' => 'password123',
        ]);

        $response->assertUnauthorized();
    }

    // === Confirm Email Change ===

    public function test_confirm_email_change_with_valid_signed_url(): void
    {
        $newEmail = 'new@example.com';
        $this->user->update(['pending_email' => $newEmail]);

        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => sha1($newEmail)]
        );

        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $response = $this->getJson($path.'?'.$query);

        $response->assertOk()
            ->assertJson([
                'data' => ['email_changed' => true],
                'message' => 'Votre adresse email a été mise à jour avec succès.',
            ]);

        $this->user->refresh();
        $this->assertEquals($newEmail, $this->user->email);
        $this->assertNull($this->user->pending_email);
        $this->assertNotNull($this->user->email_verified_at);
    }

    public function test_confirm_email_change_rejects_expired_link(): void
    {
        $newEmail = 'new@example.com';
        $this->user->update(['pending_email' => $newEmail]);

        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->subMinutes(1),
            ['id' => $this->user->id, 'hash' => sha1($newEmail)]
        );

        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $response = $this->getJson($path.'?'.$query);

        $response->assertForbidden()
            ->assertJson([
                'error' => ['code' => 'CONFIRMATION_LINK_EXPIRED'],
            ]);

        $this->assertEquals('old@example.com', $this->user->fresh()->email);
    }

    public function test_confirm_email_change_rejects_invalid_hash(): void
    {
        $this->user->update(['pending_email' => 'new@example.com']);

        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => 'invalid-hash']
        );

        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $response = $this->getJson($path.'?'.$query);

        $response->assertForbidden()
            ->assertJson([
                'error' => ['code' => 'INVALID_CONFIRMATION_LINK'],
            ]);
    }

    public function test_confirm_email_change_rejects_when_no_pending_email(): void
    {
        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => sha1('any@example.com')]
        );

        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $response = $this->getJson($path.'?'.$query);

        $response->assertBadRequest()
            ->assertJson([
                'error' => ['code' => 'NO_PENDING_EMAIL_CHANGE'],
            ]);
    }

    public function test_confirm_email_change_rejects_if_email_taken_meanwhile(): void
    {
        $newEmail = 'new@example.com';
        $this->user->update(['pending_email' => $newEmail]);

        // Another user takes the email in the meantime
        User::factory()->create(['email' => $newEmail]);

        $signedUrl = URL::temporarySignedRoute(
            'email-change.confirm',
            now()->addMinutes(60),
            ['id' => $this->user->id, 'hash' => sha1($newEmail)]
        );

        $path = parse_url($signedUrl, PHP_URL_PATH);
        $query = parse_url($signedUrl, PHP_URL_QUERY);

        $response = $this->getJson($path.'?'.$query);

        $response->assertConflict()
            ->assertJson([
                'error' => ['code' => 'EMAIL_ALREADY_TAKEN'],
            ]);

        $this->assertEquals('old@example.com', $this->user->fresh()->email);
    }

    // === Cancel Email Change ===

    public function test_cancel_email_change_clears_pending_email(): void
    {
        $this->user->update(['pending_email' => 'new@example.com']);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/email/change');

        $response->assertOk()
            ->assertJson([
                'data' => ['cancelled' => true],
                'message' => 'La demande de changement d\'email a été annulée.',
            ]);

        $this->assertNull($this->user->fresh()->pending_email);
    }

    // === Status ===

    public function test_status_returns_pending_email_when_exists(): void
    {
        $this->user->update(['pending_email' => 'new@example.com']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/email/change/status');

        $response->assertOk()
            ->assertJson([
                'data' => ['pending_email' => 'new@example.com'],
            ]);
    }

    public function test_status_returns_null_when_no_pending_email(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/email/change/status');

        $response->assertOk()
            ->assertJson([
                'data' => ['pending_email' => null],
            ]);
    }
}
