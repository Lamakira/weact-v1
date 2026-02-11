<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Face;
use App\Models\Mission;
use App\Models\Producer;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Event::fake([Verified::class]);
    }

    // === Verification Status Tests ===

    public function test_returns_verification_status_for_authenticated_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/email/verification-status');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'verified' => false,
                    'email_verified_at' => null,
                ],
            ]);
    }

    public function test_returns_verified_status_when_email_is_verified(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/email/verification-status');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'verified' => true,
                ],
            ]);
    }

    // === Resend Verification Email Tests ===

    public function test_sends_verification_email_to_unverified_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/email/verification-notification');

        $response->assertOk()
            ->assertJson([
                'data' => ['sent' => true],
                'message' => 'Un nouveau lien de vérification a été envoyé.',
            ]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    public function test_does_not_send_email_when_already_verified(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/email/verification-notification');

        $response->assertOk()
            ->assertJson([
                'data' => ['verified' => true],
                'message' => 'Votre email est déjà vérifié.',
            ]);

        Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    }

    public function test_resend_verification_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/email/verification-notification');

        $response->assertUnauthorized();
    }

    // === Verify Email Tests ===

    public function test_verifies_email_with_valid_signed_url(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        $response = $this->getJson($path . '?' . $query);

        $response->assertOk()
            ->assertJson([
                'data' => ['verified' => true],
                'message' => 'Votre email a été vérifié avec succès.',
            ]);

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Event::assertDispatched(Verified::class);
    }

    public function test_returns_error_for_expired_link(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->subMinutes(1), // Expired
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        $response = $this->getJson($path . '?' . $query);

        // Controller returns 403 with our custom error code
        $response->assertForbidden()
            ->assertJson([
                'error' => [
                    'code' => 'VERIFICATION_LINK_EXPIRED',
                ],
            ]);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_returns_error_for_invalid_hash(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => 'invalid-hash']
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        $response = $this->getJson($path . '?' . $query);

        $response->assertForbidden()
            ->assertJson([
                'error' => [
                    'code' => 'INVALID_VERIFICATION_LINK',
                ],
            ]);
    }

    public function test_returns_success_when_already_verified(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => now(),
        ]);

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $path = parse_url($verificationUrl, PHP_URL_PATH);
        $query = parse_url($verificationUrl, PHP_URL_QUERY);

        $response = $this->getJson($path . '?' . $query);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'verified' => true,
                    'already_verified' => true,
                ],
            ]);
    }

    // === Email Verification Gate Tests ===

    public function test_blocks_unverified_face_from_applying_to_mission(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);

        $producer = Producer::factory()->create();
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->id}/apply");

        $response->assertForbidden()
            ->assertJson([
                'error' => [
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Vous devez vérifier votre email pour effectuer cette action.',
                ],
            ]);
    }

    public function test_allows_verified_face_to_apply_to_mission(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => now(),
        ]);

        $producer = Producer::factory()->create();
        $mission = Mission::factory()->create([
            'producer_id' => $producer->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/face/missions/{$mission->id}/apply");

        // Should not be blocked by email verification (status !== 403)
        $this->assertNotEquals(403, $response->status());
    }

    public function test_blocks_unverified_producer_from_posting_mission(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/producer/missions', [
                'title' => 'Test Mission',
                'description' => 'Test description',
            ]);

        $response->assertForbidden()
            ->assertJson([
                'error' => [
                    'code' => 'EMAIL_NOT_VERIFIED',
                    'message' => 'Vous devez vérifier votre email pour effectuer cette action.',
                ],
            ]);
    }

    public function test_allows_verified_producer_to_post_mission(): void
    {
        $producer = Producer::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Producer::class,
            'userable_id' => $producer->id,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/producer/missions', [
                'title' => 'Test Mission',
                'description' => 'Test description',
            ]);

        // Should not be blocked by email verification (status !== 403)
        $this->assertNotEquals(403, $response->status());
    }

    // === User Response Tests ===

    public function test_user_response_includes_email_verified_field(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'email',
                    'email_verified',
                    'email_verified_at',
                    'userable_type',
                    'userable_id',
                ],
            ])
            ->assertJson([
                'data' => [
                    'email_verified' => true,
                ],
            ]);
    }

    public function test_user_response_shows_email_verified_false_for_unverified_user(): void
    {
        $face = Face::factory()->create();
        $user = User::factory()->create([
            'userable_type' => Face::class,
            'userable_id' => $face->id,
            'email_verified_at' => null,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/user');

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'email_verified' => false,
                    'email_verified_at' => null,
                ],
            ]);
    }
}
